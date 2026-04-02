<?php
/**
 * Attribution Calculator Class
 * 
 * Handles calculations for conversion rates, ROI, and attribution metrics
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GR_AttributionCalculator
 * 
 * Provides calculation methods for attribution analytics
 */
class GR_AttributionCalculator {
    
    /**
     * Calculate conversion rates for attribution data
     * 
     * @param array $attribution_data Attribution data from cache
     * @param array $traffic_data Optional traffic data for more accurate conversion rates
     * @return array Enhanced data with conversion rates
     */
    public static function calculate_conversion_rates($attribution_data, $traffic_data = array()) {
        $calculated_data = array();
        
        foreach ($attribution_data as $item) {
            $entries = intval($item['entries']);
            $group_name = isset($item['group_name']) ? $item['group_name'] : $item['date_group'];
            
            // Initialize conversion rate
            $conversion_rate = 0;
            $traffic_count = 0;
            
            // Check if we have traffic data for this group
            if (!empty($traffic_data) && isset($traffic_data[$group_name])) {
                $traffic_count = intval($traffic_data[$group_name]);
                if ($traffic_count > 0) {
                    $conversion_rate = ($entries / $traffic_count) * 100;
                }
            } else {
                // If no traffic data, use relative conversion rate based on total entries
                $total_entries = array_sum(array_column($attribution_data, 'entries'));
                if ($total_entries > 0) {
                    $conversion_rate = ($entries / $total_entries) * 100;
                }
            }
            
            // Add calculated fields to the item
            $calculated_item = $item;
            $calculated_item['conversion_rate'] = round($conversion_rate, 2);
            $calculated_item['traffic_count'] = $traffic_count;
            $calculated_item['has_traffic_data'] = !empty($traffic_data) && isset($traffic_data[$group_name]);
            
            $calculated_data[] = $calculated_item;
        }
        
        return $calculated_data;
    }
    
    /**
     * Calculate ROI for attribution data
     * 
     * @param array $attribution_data Attribution data with conversion rates
     * @param array $cost_data Channel cost data
     * @return array Enhanced data with ROI calculations
     */
    public static function calculate_roi($attribution_data, $cost_data = array()) {
        $calculated_data = array();
        
        foreach ($attribution_data as $item) {
            $entries = intval($item['entries']);
            $revenue = floatval($item['total_revenue']);
            $group_name = isset($item['group_name']) ? $item['group_name'] : $item['date_group'];
            
            // Initialize cost and ROI
            $total_cost = 0;
            $cost_per_acquisition = 0;
            $roi = 0;
            $roi_status = 'unknown';
            
            // Look up cost data for this group
            $cost_info = self::get_cost_for_group($group_name, $cost_data);
            
            if ($cost_info) {
                $cost_per_acquisition = floatval($cost_info['cost_per_acquisition']);
                $total_cost = $cost_per_acquisition * $entries;
                
                // Calculate ROI: (Revenue - Cost) / Cost * 100
                if ($total_cost > 0) {
                    $roi = (($revenue - $total_cost) / $total_cost) * 100;
                    
                    if ($roi > 0) {
                        $roi_status = 'positive';
                    } elseif ($roi < 0) {
                        $roi_status = 'negative';
                    } else {
                        $roi_status = 'neutral';
                    }
                } elseif ($revenue > 0) {
                    // If no cost but there's revenue, it's infinite ROI
                    $roi = 100;
                    $roi_status = 'positive';
                }
            }
            
            // Add calculated fields
            $calculated_item = $item;
            $calculated_item['cost_per_acquisition'] = $cost_per_acquisition;
            $calculated_item['total_cost'] = round($total_cost, 2);
            $calculated_item['roi'] = round($roi, 2);
            $calculated_item['roi_status'] = $roi_status;
            $calculated_item['profit'] = round($revenue - $total_cost, 2);
            $calculated_item['has_cost_data'] = !empty($cost_info);
            
            $calculated_data[] = $calculated_item;
        }
        
        return $calculated_data;
    }
    
    /**
     * Calculate attribution performance metrics
     * 
     * @param array $attribution_data Attribution data
     * @param array $comparison_data Optional comparison data
     * @return array Performance metrics
     */
    public static function calculate_performance_metrics($attribution_data, $comparison_data = array()) {
        $metrics = array();
        
        // Calculate totals
        $total_entries = array_sum(array_column($attribution_data, 'entries'));
        $total_revenue = array_sum(array_column($attribution_data, 'total_revenue'));
        $total_cost = array_sum(array_column($attribution_data, 'total_cost'));
        
        // Basic metrics
        $metrics['total_entries'] = $total_entries;
        $metrics['total_revenue'] = round($total_revenue, 2);
        $metrics['total_cost'] = round($total_cost, 2);
        $metrics['total_profit'] = round($total_revenue - $total_cost, 2);
        $metrics['avg_revenue_per_entry'] = $total_entries > 0 ? round($total_revenue / $total_entries, 2) : 0;
        $metrics['avg_cost_per_entry'] = $total_entries > 0 ? round($total_cost / $total_entries, 2) : 0;
        $metrics['overall_roi'] = $total_cost > 0 ? round((($total_revenue - $total_cost) / $total_cost) * 100, 2) : 0;
        
        // Channel performance analysis
        $metrics['top_performing_channels'] = self::get_top_performing_channels($attribution_data, 5);
        $metrics['underperforming_channels'] = self::get_underperforming_channels($attribution_data, 3);
        
        // Trend analysis (if comparison data provided)
        if (!empty($comparison_data)) {
            $metrics['trends'] = self::calculate_trends($attribution_data, $comparison_data);
        }
        
        // Efficiency metrics
        $metrics['efficiency_score'] = self::calculate_efficiency_score($attribution_data);
        $metrics['diversification_index'] = self::calculate_diversification_index($attribution_data);
        
        return $metrics;
    }
    
    /**
     * Get cost information for a specific group
     * 
     * @param string $group_name Group name (channel, source, etc.)
     * @param array $cost_data Available cost data
     * @return array|null Cost information
     */
    private static function get_cost_for_group($group_name, $cost_data) {
        // Direct match
        if (isset($cost_data[$group_name])) {
            return $cost_data[$group_name];
        }
        
        // Fuzzy matching for similar names
        foreach ($cost_data as $cost_key => $cost_info) {
            if (stripos($group_name, $cost_key) !== false || stripos($cost_key, $group_name) !== false) {
                return $cost_info;
            }
        }
        
        return null;
    }
    
    /**
     * Get top performing channels by ROI
     * 
     * @param array $attribution_data Attribution data
     * @param int $limit Number of channels to return
     * @return array Top performing channels
     */
    private static function get_top_performing_channels($attribution_data, $limit = 5) {
        // Filter items with ROI data and sort by ROI
        $channels_with_roi = array_filter($attribution_data, function($item) {
            return isset($item['roi']) && $item['has_cost_data'];
        });
        
        usort($channels_with_roi, function($a, $b) {
            return $b['roi'] <=> $a['roi'];
        });
        
        return array_slice($channels_with_roi, 0, $limit);
    }
    
    /**
     * Get underperforming channels
     * 
     * @param array $attribution_data Attribution data
     * @param int $limit Number of channels to return
     * @return array Underperforming channels
     */
    private static function get_underperforming_channels($attribution_data, $limit = 3) {
        // Filter items with negative ROI or very low conversion rates
        $underperforming = array_filter($attribution_data, function($item) {
            $has_negative_roi = isset($item['roi']) && $item['roi'] < 0;
            $has_low_conversion = isset($item['conversion_rate']) && $item['conversion_rate'] < 1;
            return $has_negative_roi || $has_low_conversion;
        });
        
        // Sort by worst ROI first
        usort($underperforming, function($a, $b) {
            $roi_a = isset($a['roi']) ? $a['roi'] : 0;
            $roi_b = isset($b['roi']) ? $b['roi'] : 0;
            return $roi_a <=> $roi_b;
        });
        
        return array_slice($underperforming, 0, $limit);
    }
    
    /**
     * Calculate trends between current and comparison data
     * 
     * @param array $current_data Current period data
     * @param array $comparison_data Previous period data
     * @return array Trend analysis
     */
    private static function calculate_trends($current_data, $comparison_data) {
        $trends = array();
        
        $current_totals = self::calculate_totals($current_data);
        $comparison_totals = self::calculate_totals($comparison_data);
        
        // Calculate percentage changes
        $trends['entries_change'] = self::calculate_percentage_change(
            $comparison_totals['entries'], 
            $current_totals['entries']
        );
        
        $trends['revenue_change'] = self::calculate_percentage_change(
            $comparison_totals['revenue'], 
            $current_totals['revenue']
        );
        
        $trends['roi_change'] = self::calculate_percentage_change(
            $comparison_totals['roi'], 
            $current_totals['roi']
        );
        
        return $trends;
    }
    
    /**
     * Calculate efficiency score based on conversion rate and ROI
     * 
     * @param array $attribution_data Attribution data
     * @return float Efficiency score (0-100)
     */
    private static function calculate_efficiency_score($attribution_data) {
        if (empty($attribution_data)) {
            return 0;
        }
        
        $total_score = 0;
        $scored_items = 0;
        
        foreach ($attribution_data as $item) {
            $conversion_rate = isset($item['conversion_rate']) ? floatval($item['conversion_rate']) : 0;
            $roi = isset($item['roi']) ? floatval($item['roi']) : 0;
            $entries = intval($item['entries']);
            
            if ($entries > 0) {
                // Weight by number of entries
                $weight = $entries;
                
                // Conversion rate score (0-50 points)
                $conversion_score = min($conversion_rate * 5, 50);
                
                // ROI score (0-50 points)
                $roi_score = $roi > 0 ? min($roi / 2, 50) : 0;
                
                $item_score = ($conversion_score + $roi_score) * $weight;
                $total_score += $item_score;
                $scored_items += $weight;
            }
        }
        
        return $scored_items > 0 ? round($total_score / $scored_items, 2) : 0;
    }
    
    /**
     * Calculate diversification index (how spread out the traffic sources are)
     * 
     * @param array $attribution_data Attribution data
     * @return float Diversification index (0-1, where 1 is perfectly diversified)
     */
    private static function calculate_diversification_index($attribution_data) {
        if (empty($attribution_data)) {
            return 0;
        }
        
        $total_entries = array_sum(array_column($attribution_data, 'entries'));
        if ($total_entries == 0) {
            return 0;
        }
        
        // Calculate Herfindahl-Hirschman Index
        $hhi = 0;
        foreach ($attribution_data as $item) {
            $market_share = intval($item['entries']) / $total_entries;
            $hhi += pow($market_share, 2);
        }
        
        // Convert to diversification index (1 - normalized HHI)
        $max_hhi = 1; // Maximum HHI (monopoly)
        $min_hhi = 1 / count($attribution_data); // Minimum HHI (perfectly diversified)
        
        if ($max_hhi > $min_hhi) {
            $diversification_index = 1 - (($hhi - $min_hhi) / ($max_hhi - $min_hhi));
        } else {
            $diversification_index = 1;
        }
        
        return round($diversification_index, 3);
    }
    
    /**
     * Calculate totals for a dataset
     * 
     * @param array $data Attribution data
     * @return array Totals
     */
    private static function calculate_totals($data) {
        return array(
            'entries' => array_sum(array_column($data, 'entries')),
            'revenue' => array_sum(array_column($data, 'total_revenue')),
            'cost' => array_sum(array_column($data, 'total_cost')),
            'roi' => self::calculate_weighted_average_roi($data)
        );
    }
    
    /**
     * Calculate weighted average ROI
     * 
     * @param array $data Attribution data
     * @return float Weighted average ROI
     */
    private static function calculate_weighted_average_roi($data) {
        $total_cost = 0;
        $total_revenue = 0;
        
        foreach ($data as $item) {
            $total_cost += isset($item['total_cost']) ? floatval($item['total_cost']) : 0;
            $total_revenue += floatval($item['total_revenue']);
        }
        
        if ($total_cost > 0) {
            return (($total_revenue - $total_cost) / $total_cost) * 100;
        }
        
        return 0;
    }
    
    /**
     * Calculate percentage change between two values
     * 
     * @param float $old_value Previous value
     * @param float $new_value Current value
     * @return float Percentage change
     */
    private static function calculate_percentage_change($old_value, $new_value) {
        if ($old_value == 0) {
            return $new_value > 0 ? 100 : 0;
        }
        
        return round((($new_value - $old_value) / $old_value) * 100, 2);
    }
    
    /**
     * Generate attribution insights and recommendations
     * 
     * @param array $attribution_data Attribution data with calculations
     * @return array Insights and recommendations
     */
    public static function generate_insights($attribution_data) {
        $insights = array(
            'recommendations' => array(),
            'warnings' => array(),
            'opportunities' => array()
        );
        
        $performance_metrics = self::calculate_performance_metrics($attribution_data);
        
        // Analyze overall performance
        if ($performance_metrics['overall_roi'] < 0) {
            $insights['warnings'][] = array(
                'type' => 'negative_roi',
                'message' => 'Overall ROI is negative. Consider reducing spend on underperforming channels.',
                'severity' => 'high'
            );
        }
        
        // Check for over-dependence on single channel
        if ($performance_metrics['diversification_index'] < 0.3) {
            $insights['warnings'][] = array(
                'type' => 'low_diversification',
                'message' => 'Traffic is heavily concentrated in few channels. Consider diversifying to reduce risk.',
                'severity' => 'medium'
            );
        }
        
        // Identify high-performing channels to scale
        if (!empty($performance_metrics['top_performing_channels'])) {
            $top_channel = $performance_metrics['top_performing_channels'][0];
            if (isset($top_channel['roi']) && $top_channel['roi'] > 100) {
                $insights['opportunities'][] = array(
                    'type' => 'scale_winner',
                    'message' => sprintf(
                        'Channel "%s" has excellent ROI (%.1f%%). Consider increasing investment.',
                        $top_channel['group_name'] ?? $top_channel['date_group'],
                        $top_channel['roi']
                    ),
                    'priority' => 'high'
                );
            }
        }
        
        // Flag underperforming channels
        if (!empty($performance_metrics['underperforming_channels'])) {
            foreach ($performance_metrics['underperforming_channels'] as $channel) {
                $insights['recommendations'][] = array(
                    'type' => 'optimize_channel',
                    'message' => sprintf(
                        'Channel "%s" needs optimization - low performance detected.',
                        $channel['group_name'] ?? $channel['date_group']
                    ),
                    'action' => 'Review targeting, messaging, or reduce spend.'
                );
            }
        }
        
        return $insights;
    }
    
    /**
     * Compare two attribution datasets
     * 
     * @param array $dataset_a First dataset
     * @param array $dataset_b Second dataset
     * @param string $comparison_type Type of comparison ('forms', 'channels', etc.)
     * @return array Comparison results
     */
    public static function compare_datasets($dataset_a, $dataset_b, $comparison_type = 'general') {
        $comparison = array(
            'type' => $comparison_type,
            'dataset_a' => self::calculate_performance_metrics($dataset_a),
            'dataset_b' => self::calculate_performance_metrics($dataset_b),
            'winner' => null,
            'differences' => array(),
            'significance' => 'unknown'
        );
        
        // Determine winner based on overall performance
        $score_a = self::calculate_overall_performance_score($comparison['dataset_a']);
        $score_b = self::calculate_overall_performance_score($comparison['dataset_b']);
        
        if ($score_a > $score_b) {
            $comparison['winner'] = 'a';
        } elseif ($score_b > $score_a) {
            $comparison['winner'] = 'b';
        } else {
            $comparison['winner'] = 'tie';
        }
        
        // Calculate differences
        $comparison['differences'] = array(
            'entries' => $comparison['dataset_b']['total_entries'] - $comparison['dataset_a']['total_entries'],
            'revenue' => $comparison['dataset_b']['total_revenue'] - $comparison['dataset_a']['total_revenue'],
            'roi' => $comparison['dataset_b']['overall_roi'] - $comparison['dataset_a']['overall_roi'],
            'efficiency' => $comparison['dataset_b']['efficiency_score'] - $comparison['dataset_a']['efficiency_score']
        );
        
        // Basic statistical significance (simplified)
        $comparison['significance'] = self::assess_statistical_significance($dataset_a, $dataset_b);
        
        return $comparison;
    }
    
    /**
     * Calculate overall performance score for comparison
     * 
     * @param array $metrics Performance metrics
     * @return float Performance score
     */
    private static function calculate_overall_performance_score($metrics) {
        $score = 0;
        
        // ROI weight: 40%
        $score += ($metrics['overall_roi'] / 100) * 40;
        
        // Efficiency weight: 30%
        $score += ($metrics['efficiency_score'] / 100) * 30;
        
        // Revenue weight: 20%
        $score += min($metrics['total_revenue'] / 1000, 20); // Cap at 20 points
        
        // Diversification weight: 10%
        $score += $metrics['diversification_index'] * 10;
        
        return round($score, 2);
    }
    
    /**
     * Assess statistical significance (simplified)
     * 
     * @param array $dataset_a First dataset
     * @param array $dataset_b Second dataset
     * @return string Significance level
     */
    private static function assess_statistical_significance($dataset_a, $dataset_b) {
        $entries_a = array_sum(array_column($dataset_a, 'entries'));
        $entries_b = array_sum(array_column($dataset_b, 'entries'));
        
        // Simple sample size check
        if ($entries_a < 30 || $entries_b < 30) {
            return 'insufficient_data';
        }
        
        $revenue_a = array_sum(array_column($dataset_a, 'total_revenue'));
        $revenue_b = array_sum(array_column($dataset_b, 'total_revenue'));
        
        $avg_revenue_a = $entries_a > 0 ? $revenue_a / $entries_a : 0;
        $avg_revenue_b = $entries_b > 0 ? $revenue_b / $entries_b : 0;
        
        $difference_percentage = $avg_revenue_a > 0 ? abs(($avg_revenue_b - $avg_revenue_a) / $avg_revenue_a) * 100 : 0;
        
        if ($difference_percentage > 20) {
            return 'high';
        } elseif ($difference_percentage > 10) {
            return 'medium';
        } elseif ($difference_percentage > 5) {
            return 'low';
        } else {
            return 'not_significant';
        }
    }
}
