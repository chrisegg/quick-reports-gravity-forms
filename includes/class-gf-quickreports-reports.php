<?php
/**
 * Report data, exports, and helpers for Quick Reports.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GF_QuickReports_Reports {

	/**
	 * Capability required to view reports, export, and use AJAX actions.
	 */
	public static function capability() {
		return 'gravityforms_view_entries';
	}

	public static function user_can_reports() {
		return current_user_can( self::capability() );
	}

	/**
	 * Allow only safe data-URI images for Dompdf (PNG/JPEG base64).
	 *
	 * @param mixed $raw Raw POST value.
	 * @return string Sanitized data URI or empty string.
	 */
	public static function sanitize_chart_data_uri( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$raw = trim( $raw );
		$max = 5 * 1024 * 1024;
		if ( strlen( $raw ) > $max ) {
			return '';
		}
		if ( ! preg_match( '#^data:image/(png|jpeg);base64,([a-zA-Z0-9+/=\r\n]+)$#', $raw, $m ) ) {
			return '';
		}
		$decoded = base64_decode( preg_replace( '/\s+/', '', $m[2] ), true );
		if ( false === $decoded || strlen( $decoded ) > $max ) {
			return '';
		}
		return $raw;
	}

	/**
	 * Revenue total for a single entry (active entries only).
	 *
	 * @param array      $entry Entry array.
	 * @param array|null $form  Form array or null to load.
	 * @return float
	 */
	public static function revenue_for_entry( $entry, $form = null ) {
		if ( empty( $entry ) || ! is_array( $entry ) ) {
			return 0.0;
		}
		if ( isset( $entry['status'] ) && 'active' !== $entry['status'] ) {
			return 0.0;
		}
		if ( null === $form ) {
			$form = GFAPI::get_form( rgar( $entry, 'form_id' ) );
		}
		if ( ! $form ) {
			return 0.0;
		}
		$products = GFCommon::get_product_fields( $form, $entry );
		$total    = 0.0;
		if ( ! empty( $products['products'] ) ) {
			foreach ( $products['products'] as $product ) {
				$price = GFCommon::to_number( $product['price'] );
				if ( is_numeric( $price ) ) {
					$qty = isset( $product['quantity'] ) ? (float) $product['quantity'] : 1;
					$total += (float) $price * $qty;
				}
			}
		}
		return round( $total, 2 );
	}

	/**
	 * Chart series: entry counts or daily revenue (aligned date keys Y-m-d for per_day).
	 *
	 * @param int|string $form_id Form ID, 0 empty, or 'all'.
	 * @param string     $start_date Y-m-d.
	 * @param string     $end_date Y-m-d.
	 * @param string     $mode per_day|total.
	 * @param string     $metric entries|revenue.
	 * @return array{labels: string[], data: array}
	 */
	public static function get_chart_data( $form_id, $start_date = '', $end_date = '', $mode = 'per_day', $metric = 'entries' ) {
		if ( '' === $form_id || null === $form_id || false === $form_id ) {
			return array( 'labels' => array(), 'data' => array() );
		}
		if ( ( is_int( $form_id ) || ( is_string( $form_id ) && ctype_digit( $form_id ) ) ) && (int) $form_id === 0 ) {
			return array( 'labels' => array(), 'data' => array() );
		}

		if ( 'revenue' === $metric ) {
			return self::get_chart_data_revenue( $form_id, $start_date, $end_date, $mode );
		}

		global $wpdb;

		$table_name   = GFFormsModel::get_entry_table_name();
		$where_parts  = array();
		$where_parts[] = $wpdb->prepare( 'status = %s', 'active' );

		if ( 'all' !== $form_id ) {
			$where_parts[] = $wpdb->prepare( 'form_id = %d', $form_id );
		}

		if ( ! empty( $start_date ) ) {
			$where_parts[] = $wpdb->prepare( 'date_created >= %s', $start_date . ' 00:00:00' );
		}
		if ( ! empty( $end_date ) ) {
			$where_parts[] = $wpdb->prepare( 'date_created <= %s', $end_date . ' 23:59:59' );
		}

		$where = implode( ' AND ', $where_parts );

		if ( 'total' === $mode ) {
			$query  = "SELECT COUNT(*) as count FROM {$table_name} WHERE {$where}";
			$result = $wpdb->get_var( $query );
			return array(
				'labels' => array( 'Total' ),
				'data'   => array( (int) $result ),
			);
		}

		$query   = "SELECT DATE(date_created) as date, COUNT(*) as count
			FROM {$table_name}
			WHERE {$where}
			GROUP BY DATE(date_created)
			ORDER BY date ASC";
		$results = $wpdb->get_results( $query );

		$labels = array();
		$data   = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$labels[] = gmdate( 'Y-m-d', strtotime( $row->date ) );
				$data[]   = (int) $row->count;
			}
		}

		return array( 'labels' => $labels, 'data' => $data );
	}

	/**
	 * Revenue chart data using Y-m-d labels to match entry charts.
	 *
	 * @param int|string $form_id Form ID or 'all'.
	 * @param string     $start_date Y-m-d.
	 * @param string     $end_date Y-m-d.
	 * @param string     $mode per_day|total.
	 * @return array{labels: string[], data: array}
	 */
	private static function get_chart_data_revenue( $form_id, $start_date, $end_date, $mode ) {
		if ( 'total' === $mode ) {
			$total = 0.0;
			if ( 'all' === $form_id ) {
				foreach ( GFAPI::get_forms() as $form ) {
					$total += self::sum_revenue_for_form_in_range( $form['id'], $start_date, $end_date );
				}
			} else {
				$total = self::sum_revenue_for_form_in_range( $form_id, $start_date, $end_date );
			}
			return array(
				'labels' => array( 'Total' ),
				'data'   => array( round( $total, 2 ) ),
			);
		}

		$by_day = self::get_daily_revenue_ymd( $form_id, $start_date, $end_date );
		return array(
			'labels' => array_keys( $by_day ),
			'data'   => array_values( $by_day ),
		);
	}

	/**
	 * Daily revenue keyed by Y-m-d for chart alignment.
	 *
	 * @param int|string $form_id Form ID or 'all'.
	 * @param string     $start_date Y-m-d.
	 * @param string     $end_date Y-m-d.
	 * @return array<string,float>
	 */
	private static function get_daily_revenue_ymd( $form_id, $start_date, $end_date ) {
		$days = array();
		if ( ! $start_date || ! $end_date ) {
			return $days;
		}
		$current_ts = strtotime( $start_date );
		$end_ts     = strtotime( $end_date );
		while ( $current_ts <= $end_ts ) {
			$days[ gmdate( 'Y-m-d', $current_ts ) ] = 0.0;
			$current_ts                            = strtotime( '+1 day', $current_ts );
		}

		$form_ids = self::resolve_form_ids_for_revenue( $form_id );
		if ( empty( $form_ids ) ) {
			return $days;
		}

		$search_criteria = array(
			'status'     => 'active',
			'start_date' => $start_date . ' 00:00:00',
			'end_date'   => $end_date . ' 23:59:59',
		);

		$form_cache = array();
		$offset     = 0;
		$page_size  = 500;

		while ( true ) {
			$entries = GFAPI::get_entries(
				$form_ids,
				$search_criteria,
				null,
				array(
					'offset'     => $offset,
					'page_size'  => $page_size,
				)
			);
			if ( empty( $entries ) || is_wp_error( $entries ) ) {
				break;
			}
			foreach ( $entries as $entry ) {
				$ymd = gmdate( 'Y-m-d', strtotime( rgar( $entry, 'date_created' ) ) );
				if ( ! isset( $days[ $ymd ] ) ) {
					continue;
				}
				$fid = (int) rgar( $entry, 'form_id' );
				if ( ! isset( $form_cache[ $fid ] ) ) {
					$form_cache[ $fid ] = GFAPI::get_form( $fid );
				}
				$days[ $ymd ] += self::revenue_for_entry( $entry, $form_cache[ $fid ] );
			}
			if ( count( $entries ) < $page_size ) {
				break;
			}
			$offset += $page_size;
		}

		foreach ( $days as $k => $v ) {
			$days[ $k ] = round( $v, 2 );
		}
		return $days;
	}

	/**
	 * @param int|string $form_id Form ID or 'all'.
	 * @return int[]
	 */
	private static function resolve_form_ids_for_revenue( $form_id ) {
		if ( 'all' === $form_id ) {
			$ids = array();
			foreach ( GFAPI::get_forms() as $form ) {
				$product_fields = GFCommon::get_fields_by_type( $form, array( 'product' ) );
				if ( ! empty( $product_fields ) ) {
					$ids[] = (int) $form['id'];
				}
			}
			return $ids;
		}
		if ( is_numeric( $form_id ) && (int) $form_id > 0 ) {
			return array( (int) $form_id );
		}
		return array();
	}

	/**
	 * Sum revenue for one form in a date range (single pass).
	 *
	 * @param int    $form_id Form ID.
	 * @param string $start_date Y-m-d.
	 * @param string $end_date Y-m-d.
	 * @return float
	 */
	private static function sum_revenue_for_form_in_range( $form_id, $start_date, $end_date ) {
		$search_criteria = array(
			'status'     => 'active',
			'start_date' => $start_date . ' 00:00:00',
			'end_date'   => $end_date . ' 23:59:59',
		);
		$form            = GFAPI::get_form( $form_id );
		if ( ! $form ) {
			return 0.0;
		}
		$total  = 0.0;
		$offset = 0;
		$size   = 500;
		while ( true ) {
			$entries = GFAPI::get_entries(
				$form_id,
				$search_criteria,
				null,
				array(
					'offset'     => $offset,
					'page_size'  => $size,
				)
			);
			if ( empty( $entries ) || is_wp_error( $entries ) ) {
				break;
			}
			foreach ( $entries as $entry ) {
				$total += self::revenue_for_entry( $entry, $form );
			}
			if ( count( $entries ) < $size ) {
				break;
			}
			$offset += $size;
		}
		return round( $total, 2 );
	}

	public static function get_recent_entries( $form_id, $limit = 10 ) {
		if ( empty( $form_id ) ) {
			return array();
		}
		$search_criteria = array( 'status' => 'active' );
		$sorting         = array(
			'key'       => 'date_created',
			'direction' => 'DESC',
		);
		$paging          = array(
			'offset'     => 0,
			'page_size'  => $limit,
		);
		return GFAPI::get_entries( $form_id, $search_criteria, $sorting, $paging );
	}

	public static function get_daily_entries( $form_id, $start_date, $end_date ) {
		$daily_entries = array();
		if ( ! $start_date || ! $end_date ) {
			return $daily_entries;
		}
		$current_date = $start_date;
		while ( strtotime( $current_date ) <= strtotime( $end_date ) ) {
			$search_criteria = array(
				'status'     => 'active',
				'start_date' => $current_date . ' 00:00:00',
				'end_date'   => $current_date . ' 23:59:59',
			);
			$count           = GFAPI::count_entries( $form_id, $search_criteria );
			$daily_entries[ gmdate( 'M j', strtotime( $current_date ) ) ] = $count;
			$current_date    = gmdate( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
		}
		return $daily_entries;
	}

	/**
	 * Daily revenue keyed by "M j" for table/summary (matches get_daily_entries keys).
	 *
	 * @param int|string $form_id Form ID or 'all'.
	 * @param string     $start_date Y-m-d.
	 * @param string     $end_date Y-m-d.
	 * @return array<string,float>
	 */
	public static function get_daily_revenue( $form_id, $start_date, $end_date ) {
		$daily_revenue = array();
		if ( ! $start_date || ! $end_date ) {
			return $daily_revenue;
		}

		$current_ts = strtotime( $start_date );
		$end_ts     = strtotime( $end_date );
		while ( $current_ts <= $end_ts ) {
			$daily_revenue[ gmdate( 'M j', $current_ts ) ] = 0.0;
			$current_ts                                    = strtotime( '+1 day', $current_ts );
		}

		$form_ids = self::resolve_form_ids_for_revenue( $form_id );
		if ( empty( $form_ids ) ) {
			return $daily_revenue;
		}

		$search_criteria = array(
			'status'     => 'active',
			'start_date' => $start_date . ' 00:00:00',
			'end_date'   => $end_date . ' 23:59:59',
		);

		$form_cache = array();
		$offset     = 0;
		$page_size  = 500;

		while ( true ) {
			$entries = GFAPI::get_entries(
				$form_ids,
				$search_criteria,
				null,
				array(
					'offset'     => $offset,
					'page_size'  => $page_size,
				)
			);
			if ( empty( $entries ) || is_wp_error( $entries ) ) {
				break;
			}
			foreach ( $entries as $entry ) {
				$key = gmdate( 'M j', strtotime( rgar( $entry, 'date_created' ) ) );
				if ( ! isset( $daily_revenue[ $key ] ) ) {
					continue;
				}
				$fid = (int) rgar( $entry, 'form_id' );
				if ( ! isset( $form_cache[ $fid ] ) ) {
					$form_cache[ $fid ] = GFAPI::get_form( $fid );
				}
				$daily_revenue[ $key ] += self::revenue_for_entry( $entry, $form_cache[ $fid ] );
			}
			if ( count( $entries ) < $page_size ) {
				break;
			}
			$offset += $page_size;
		}

		foreach ( $daily_revenue as $k => $v ) {
			$daily_revenue[ $k ] = round( $v, 2 );
		}
		return $daily_revenue;
	}

	public static function handle_csv_export() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gf_quickreports_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'gf-quickreports' ) );
		}
		if ( ! self::user_can_reports() ) {
			wp_die( esc_html__( 'Insufficient permissions', 'gf-quickreports' ) );
		}

		$form_id         = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
		$compare_form_id = isset( $_POST['compare_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['compare_form_id'] ) ) : '';
		$start_date      = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date        = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

		$search_criteria = array( 'status' => 'active' );
		if ( ! empty( $start_date ) ) {
			$search_criteria['start_date'] = $start_date . ' 00:00:00';
		}
		if ( ! empty( $end_date ) ) {
			$search_criteria['end_date'] = $end_date . ' 23:59:59';
		}

		if ( ob_get_length() ) {
			ob_clean();
		}
		if ( ini_get( 'zlib.output_compression' ) ) {
			ini_set( 'zlib.output_compression', 'Off' );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gf-quickreports-' . $form_id . '-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		if ( 'all' === $form_id ) {
			$forms = GFAPI::get_forms();
			fputcsv( $output, array( 'Form Name', 'Total Entries', 'Average Per Day', 'Total Revenue' ) );
			$total_entries = 0;
			$total_revenue = 0;
			$total_days    = 0;
			foreach ( $forms as $form ) {
				$entry_count   = GFAPI::count_entries( $form['id'], $search_criteria );
				$entries       = GFAPI::get_entries( $form['id'], $search_criteria );
				$daily_entries = self::get_daily_entries( $form['id'], $start_date, $end_date );
				$days_count    = count( $daily_entries );
				$avg_per_day   = $days_count > 0 ? $entry_count / $days_count : 0;
				$form_revenue  = 0;
				$product_fields = array();
				foreach ( $form['fields'] as $field ) {
					if ( isset( $field['type'] ) && 'product' === $field['type'] ) {
						$product_fields[] = $field['id'];
					}
				}
				if ( ! empty( $product_fields ) && ! empty( $entries ) ) {
					foreach ( $entries as $entry ) {
						foreach ( $product_fields as $pid ) {
							$val = rgar( $entry, $pid );
							if ( is_numeric( $val ) ) {
								$form_revenue += floatval( $val );
							} elseif ( is_array( $val ) && isset( $val['price'] ) ) {
								$form_revenue += floatval( $val['price'] );
							} elseif ( is_string( $val ) ) {
								if ( preg_match( '/([\d\.,]+)/', $val, $matches ) ) {
									$form_revenue += floatval( str_replace( ',', '', $matches[1] ) );
								}
							}
						}
					}
				}
				fputcsv(
					$output,
					array(
						$form['title'],
						$entry_count,
						number_format( $avg_per_day, 2 ),
						! empty( $product_fields ) ? '$' . number_format( $form_revenue, 2 ) : 'N/A',
					)
				);
				$total_entries += $entry_count;
				$total_revenue += $form_revenue;
				$total_days     = max( $total_days, $days_count );
			}
			fputcsv( $output, array( '' ) );
			fputcsv(
				$output,
				array(
					'TOTAL',
					$total_entries,
					$total_days > 0 ? number_format( $total_entries / $total_days, 2 ) : '0.00',
					'$' . number_format( $total_revenue, 2 ),
				)
			);
		} else {
			$form            = GFAPI::get_form( $form_id );
			$entries         = GFAPI::get_entries( $form_id, $search_criteria );
			$entry_count     = count( $entries );
			$daily_entries   = self::get_daily_entries( $form_id, $start_date, $end_date );
			$days_count      = count( $daily_entries );
			$avg_per_day     = $days_count > 0 ? $entry_count / $days_count : 0;
			$total_revenue   = 0;
			$product_fields  = array();
			foreach ( $form['fields'] as $field ) {
				if ( isset( $field['type'] ) && 'product' === $field['type'] ) {
					$product_fields[] = $field['id'];
				}
			}
			if ( ! empty( $product_fields ) && ! empty( $entries ) ) {
				foreach ( $entries as $entry ) {
					foreach ( $product_fields as $pid ) {
						$val = rgar( $entry, $pid );
						if ( is_numeric( $val ) ) {
							$total_revenue += floatval( $val );
						} elseif ( is_array( $val ) && isset( $val['price'] ) ) {
							$total_revenue += floatval( $val['price'] );
						} elseif ( is_string( $val ) ) {
							if ( preg_match( '/([\d\.,]+)/', $val, $matches ) ) {
								$total_revenue += floatval( str_replace( ',', '', $matches[1] ) );
							}
						}
					}
				}
			}
			fputcsv( $output, array( 'Form Name', 'Total Entries', 'Average Per Day', 'Total Revenue' ) );
			fputcsv(
				$output,
				array(
					$form['title'],
					$entry_count,
					number_format( $avg_per_day, 2 ),
					! empty( $product_fields ) ? '$' . number_format( $total_revenue, 2 ) : 'N/A',
				)
			);
			if ( $compare_form_id ) {
				$compare_form         = GFAPI::get_form( $compare_form_id );
				$compare_entries      = GFAPI::get_entries( $compare_form_id, $search_criteria );
				$compare_entry_count  = count( $compare_entries );
				$compare_daily_entries = self::get_daily_entries( $compare_form_id, $start_date, $end_date );
				$compare_days_count   = count( $compare_daily_entries );
				$compare_avg_per_day  = $compare_days_count > 0 ? $compare_entry_count / $compare_days_count : 0;
				$compare_total_revenue = 0;
				$compare_product_fields = array();
				foreach ( $compare_form['fields'] as $field ) {
					if ( isset( $field['type'] ) && 'product' === $field['type'] ) {
						$compare_product_fields[] = $field['id'];
					}
				}
				if ( ! empty( $compare_product_fields ) && ! empty( $compare_entries ) ) {
					foreach ( $compare_entries as $entry ) {
						foreach ( $compare_product_fields as $pid ) {
							$val = rgar( $entry, $pid );
							if ( is_numeric( $val ) ) {
								$compare_total_revenue += floatval( $val );
							} elseif ( is_array( $val ) && isset( $val['price'] ) ) {
								$compare_total_revenue += floatval( $val['price'] );
							} elseif ( is_string( $val ) ) {
								if ( preg_match( '/([\d\.,]+)/', $val, $matches ) ) {
									$compare_total_revenue += floatval( str_replace( ',', '', $matches[1] ) );
								}
							}
						}
					}
				}
				fputcsv(
					$output,
					array(
						$compare_form['title'],
						$compare_entry_count,
						number_format( $compare_avg_per_day, 2 ),
						! empty( $compare_product_fields ) ? '$' . number_format( $compare_total_revenue, 2 ) : 'N/A',
					)
				);
			}
		}
		fclose( $output );
		exit;
	}

	public static function handle_pdf_export() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gf_quickreports_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'gf-quickreports' ) );
		}
		if ( ! self::user_can_reports() ) {
			wp_die( esc_html__( 'Insufficient permissions', 'gf-quickreports' ) );
		}

		$form_id              = isset( $_POST['form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['form_id'] ) ) : '';
		$compare_form_id      = isset( $_POST['compare_form_id'] ) ? sanitize_text_field( wp_unslash( $_POST['compare_form_id'] ) ) : '';
		$start_date           = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date             = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$chart_data_raw       = isset( $_POST['chart_data'] ) ? wp_unslash( $_POST['chart_data'] ) : '';
		$revenue_chart_raw    = isset( $_POST['revenue_chart_data'] ) ? wp_unslash( $_POST['revenue_chart_data'] ) : '';
		$chart_data           = self::sanitize_chart_data_uri( $chart_data_raw );
		$revenue_chart_data   = self::sanitize_chart_data_uri( $revenue_chart_raw );

		$search_criteria = array( 'status' => 'active' );
		if ( ! empty( $start_date ) ) {
			$search_criteria['start_date'] = $start_date . ' 00:00:00';
		}
		if ( ! empty( $end_date ) ) {
			$search_criteria['end_date'] = $end_date . ' 23:59:59';
		}

		$autoload = GF_QUICKREPORTS_PLUGIN_DIR . 'vendor/autoload.php';
		if ( ! is_readable( $autoload ) ) {
			wp_die( esc_html__( 'PDF export is unavailable: install Composer dependencies (vendor/autoload.php).', 'gf-quickreports' ) );
		}

		try {
			require_once $autoload;

			$dompdf = new \Dompdf\Dompdf();
			$dompdf->setPaper( 'A4', 'portrait' );

			$html = '<html><head><style>
				body { font-family: DejaVu Sans, sans-serif; margin: 20px; }
				table { width: 100%; border-collapse: collapse; margin: 20px 0; }
				th, td { padding: 8px; border: 1px solid #ddd; }
				th { background-color: #f5f5f5; }
				h1, h2 { color: #333; }
				.chart-container { margin: 20px 0; text-align: center; }
				img { max-width: 100%; height: auto; }
			</style></head><body>';

			$html .= '<h1>Gravity Forms Report</h1>';
			$html .= '<p>Date Range: ' . esc_html( gmdate( 'M j, Y', strtotime( $start_date ) ) ) . ' - ' . esc_html( gmdate( 'M j, Y', strtotime( $end_date ) ) ) . '</p>';

			if ( ! empty( $chart_data ) ) {
				$html .= '<div class="chart-container"><h3>Entries Over Time</h3><img src="' . esc_attr( $chart_data ) . '"></div>';
			}
			if ( ! empty( $revenue_chart_data ) ) {
				$html .= '<div class="chart-container"><h3>Revenue Over Time</h3><img src="' . esc_attr( $revenue_chart_data ) . '"></div>';
			}

			if ( 'all' === $form_id ) {
				$html .= '<h2>All Forms Summary</h2><table><tr><th>Form</th><th>Total Entries</th><th>Average Per Day</th><th>Total Revenue</th></tr>';
				$forms          = GFAPI::get_forms();
				$total_entries  = 0;
				$total_revenue  = 0;
				$total_days     = 0;
				foreach ( $forms as $form ) {
					$entry_count   = GFAPI::count_entries( $form['id'], $search_criteria );
					$entries       = GFAPI::get_entries( $form['id'], $search_criteria );
					$daily_entries = self::get_daily_entries( $form['id'], $start_date, $end_date );
					$days_count    = count( $daily_entries );
					$avg_per_day   = $days_count > 0 ? $entry_count / $days_count : 0;
					$form_revenue  = 0;
					$product_fields = array();
					foreach ( $form['fields'] as $field ) {
						if ( isset( $field['type'] ) && 'product' === $field['type'] ) {
							$product_fields[] = $field['id'];
						}
					}
					if ( ! empty( $product_fields ) && ! empty( $entries ) ) {
						foreach ( $entries as $entry ) {
							foreach ( $product_fields as $pid ) {
								$val = rgar( $entry, $pid );
								if ( is_numeric( $val ) ) {
									$form_revenue += floatval( $val );
								} elseif ( is_array( $val ) && isset( $val['price'] ) ) {
									$form_revenue += floatval( $val['price'] );
								} elseif ( is_string( $val ) ) {
									if ( preg_match( '/([\d\.,]+)/', $val, $matches ) ) {
										$form_revenue += floatval( str_replace( ',', '', $matches[1] ) );
									}
								}
							}
						}
					}
					$html .= sprintf(
						'<tr><td>%s</td><td>%d</td><td>%.2f</td><td>%s</td></tr>',
						esc_html( $form['title'] ),
						$entry_count,
						$avg_per_day,
						! empty( $product_fields ) ? '$' . number_format( $form_revenue, 2 ) : 'N/A'
					);
					$total_entries += $entry_count;
					$total_revenue += $form_revenue;
					$total_days     = max( $total_days, $days_count );
				}
				$html .= sprintf(
					'<tr style="font-weight: bold;"><td>TOTAL</td><td>%d</td><td>%.2f</td><td>$%s</td></tr>',
					$total_entries,
					$total_days > 0 ? $total_entries / $total_days : 0,
					number_format( $total_revenue, 2 )
				);
				$html .= '</table>';
				if ( $compare_form_id ) {
					$html .= self::pdf_compare_section( $compare_form_id, $search_criteria, $start_date, $end_date );
				}
			} else {
				$form            = GFAPI::get_form( $form_id );
				$entries         = GFAPI::get_entries( $form_id, $search_criteria );
				$entry_count     = count( $entries );
				$daily_entries   = self::get_daily_entries( $form_id, $start_date, $end_date );
				$html           .= '<h2>' . esc_html( $form['title'] ) . ' - Summary</h2><table>';
				$html           .= sprintf( '<tr><td>Total Entries</td><td>%d</td></tr>', $entry_count );
				$html           .= sprintf(
					'<tr><td>Average Per Day</td><td>%.2f</td></tr>',
					count( $daily_entries ) > 0 ? $entry_count / count( $daily_entries ) : 0
				);
				$total_revenue   = 0;
				$product_fields  = array();
				foreach ( $form['fields'] as $field ) {
					if ( isset( $field['type'] ) && 'product' === $field['type'] ) {
						$product_fields[] = $field['id'];
					}
				}
				if ( ! empty( $product_fields ) && ! empty( $entries ) ) {
					foreach ( $entries as $entry ) {
						foreach ( $product_fields as $pid ) {
							$val = rgar( $entry, $pid );
							if ( is_numeric( $val ) ) {
								$total_revenue += floatval( $val );
							} elseif ( is_array( $val ) && isset( $val['price'] ) ) {
								$total_revenue += floatval( $val['price'] );
							} elseif ( is_string( $val ) ) {
								if ( preg_match( '/([\d\.,]+)/', $val, $matches ) ) {
									$total_revenue += floatval( str_replace( ',', '', $matches[1] ) );
								}
							}
						}
					}
					$html .= sprintf( '<tr><td>Total Revenue</td><td>$%s</td></tr>', number_format( $total_revenue, 2 ) );
				}
				$html .= '</table>';
				if ( $compare_form_id ) {
					$html .= self::pdf_compare_section( $compare_form_id, $search_criteria, $start_date, $end_date );
				}
			}

			$html .= '</body></html>';
			$dompdf->loadHtml( $html );
			$dompdf->render();
			$pdf_content = $dompdf->output();
			$len         = strlen( $pdf_content );
			if ( ob_get_length() ) {
				ob_clean();
			}
			if ( ini_get( 'zlib.output_compression' ) ) {
				ini_set( 'zlib.output_compression', 'Off' );
			}
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="gf-quickreports-' . $form_id . '-' . gmdate( 'Y-m-d' ) . '.pdf"' );
			header( 'Content-Length: ' . $len );
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate' );
			header( 'Pragma: no-cache' );
			echo $pdf_content;
			exit;
		} catch ( Exception $e ) {
			wp_die( esc_html( 'Error generating PDF: ' . $e->getMessage() ) );
		}
	}

	private static function pdf_compare_section( $compare_form_id, $search_criteria, $start_date, $end_date ) {
		$compare_form         = GFAPI::get_form( $compare_form_id );
		$compare_entries      = GFAPI::get_entries( $compare_form_id, $search_criteria );
		$compare_entry_count  = count( $compare_entries );
		$compare_daily_entries = self::get_daily_entries( $compare_form_id, $start_date, $end_date );
		$html                 = '<h2>' . esc_html( $compare_form['title'] ) . ' - Summary</h2><table>';
		$html                .= sprintf( '<tr><td>Total Entries</td><td>%d</td></tr>', $compare_entry_count );
		$html                .= sprintf(
			'<tr><td>Average Per Day</td><td>%.2f</td></tr>',
			count( $compare_daily_entries ) > 0 ? $compare_entry_count / count( $compare_daily_entries ) : 0
		);
		$compare_total_revenue = 0;
		$compare_product_fields = array();
		foreach ( $compare_form['fields'] as $field ) {
			if ( isset( $field['type'] ) && 'product' === $field['type'] ) {
				$compare_product_fields[] = $field['id'];
			}
		}
		if ( ! empty( $compare_product_fields ) && ! empty( $compare_entries ) ) {
			foreach ( $compare_entries as $entry ) {
				foreach ( $compare_product_fields as $pid ) {
					$val = rgar( $entry, $pid );
					if ( is_numeric( $val ) ) {
						$compare_total_revenue += floatval( $val );
					} elseif ( is_array( $val ) && isset( $val['price'] ) ) {
						$compare_total_revenue += floatval( $val['price'] );
					} elseif ( is_string( $val ) ) {
						if ( preg_match( '/([\d\.,]+)/', $val, $matches ) ) {
							$compare_total_revenue += floatval( str_replace( ',', '', $matches[1] ) );
						}
					}
				}
			}
			$html .= sprintf( '<tr><td>Total Revenue</td><td>$%s</td></tr>', number_format( $compare_total_revenue, 2 ) );
		}
		$html .= '</table>';
		return $html;
	}

	public static function get_compare_forms() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gf_quickreports_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'gf-quickreports' ) );
		}
		if ( ! self::user_can_reports() ) {
			wp_die( esc_html__( 'Insufficient permissions', 'gf-quickreports' ) );
		}
		$selected_form = isset( $_POST['selected_form'] ) ? sanitize_text_field( wp_unslash( $_POST['selected_form'] ) ) : '';
		if ( empty( $selected_form ) || 'all' === $selected_form ) {
			wp_send_json_success( array( 'options' => array() ) );
		}
		$forms   = GFAPI::get_forms();
		$options = array();
		foreach ( $forms as $form ) {
			if ( (string) $form['id'] !== (string) $selected_form ) {
				$options[] = array(
					'value' => $form['id'],
					'label' => $form['title'],
				);
			}
		}
		wp_send_json_success( array( 'options' => $options ) );
	}

	public static function get_date_presets() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'gf_quickreports_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed', 'gf-quickreports' ) );
		}
		if ( ! self::user_can_reports() ) {
			wp_die( esc_html__( 'Insufficient permissions', 'gf-quickreports' ) );
		}
		$preset = isset( $_POST['preset'] ) ? sanitize_text_field( wp_unslash( $_POST['preset'] ) ) : '';
		$dates  = array();
		switch ( $preset ) {
			case 'today':
				$dates['start_date'] = gmdate( 'Y-m-d' );
				$dates['end_date']   = gmdate( 'Y-m-d' );
				break;
			case 'yesterday':
				$dates['start_date'] = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
				$dates['end_date']   = gmdate( 'Y-m-d', strtotime( '-1 day' ) );
				break;
			case '7days':
				$dates['start_date'] = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				$dates['end_date']   = gmdate( 'Y-m-d' );
				break;
			case '30days':
				$dates['start_date'] = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				$dates['end_date']   = gmdate( 'Y-m-d' );
				break;
			case '60days':
				$dates['start_date'] = gmdate( 'Y-m-d', strtotime( '-60 days' ) );
				$dates['end_date']   = gmdate( 'Y-m-d' );
				break;
			case '90days':
				$dates['start_date'] = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
				$dates['end_date']   = gmdate( 'Y-m-d' );
				break;
			case 'year_to_date':
				$dates['start_date'] = gmdate( 'Y-01-01' );
				$dates['end_date']   = gmdate( 'Y-m-d' );
				break;
			case 'last_year':
				$dates['start_date'] = gmdate( 'Y-01-01', strtotime( '-1 year' ) );
				$dates['end_date']   = gmdate( 'Y-12-31', strtotime( '-1 year' ) );
				break;
			default:
				$dates['start_date'] = '';
				$dates['end_date']   = '';
				break;
		}
		wp_send_json_success( $dates );
	}
}
