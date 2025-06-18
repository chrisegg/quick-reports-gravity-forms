/**
 * Gravity Forms Reports Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Initialize chart if data is available
    if (typeof chartData !== 'undefined' && chartData.labels.length > 0) {
        initializeChart();
    }
    
    // CSV Export functionality
    $('#export-csv').on('click', function(e) {
        e.preventDefault();
        exportCSV();
    });
    
    // Form validation
    $('.gf-reports-form').on('submit', function(e) {
        var formId = $('#form_id').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (!formId) {
            e.preventDefault();
            showNotice('Please select a form to generate a report.', 'error');
            return false;
        }
        
        if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
            e.preventDefault();
            showNotice('Start date cannot be after end date.', 'error');
            return false;
        }
    });
    
    // Date range presets
    addDatePresets();
    
    // Auto-submit on form change (optional)
    $('#form_id').on('change', function() {
        if ($(this).val()) {
            // Uncomment the line below to auto-submit when form is selected
            // $('.gf-reports-form').submit();
        }
    });
    
    /**
     * Initialize Chart.js for entries over time
     */
    function initializeChart() {
        var ctx = document.getElementById('entriesChart').getContext('2d');
        
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Entries',
                    data: chartData.data,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#2271b1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#2271b1',
                        borderWidth: 1,
                        cornerRadius: 4,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                return 'Date: ' + context[0].label;
                            },
                            label: function(context) {
                                return 'Entries: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#646970',
                            font: {
                                size: 12
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#646970',
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                elements: {
                    point: {
                        hoverBackgroundColor: '#2271b1'
                    }
                }
            }
        });
    }
    
    /**
     * Export CSV functionality
     */
    function exportCSV() {
        var formId = $('#form_id').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (!formId) {
            showNotice('Please select a form to export.', 'error');
            return;
        }
        
        // Show loading state
        var $button = $('#export-csv');
        var originalText = $button.text();
        $button.text('Exporting...').prop('disabled', true);
        
        // Create form data for AJAX
        var formData = new FormData();
        formData.append('action', 'gf_reports_export_csv');
        formData.append('nonce', gf_reports_ajax.nonce);
        formData.append('form_id', formId);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);
        
        // Make AJAX request
        $.ajax({
            url: gf_reports_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Create download link
                var blob = new Blob([response], { type: 'text/csv' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'gf-reports-' + formId + '-' + new Date().toISOString().split('T')[0] + '.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                showNotice('CSV export completed successfully!', 'success');
            },
            error: function(xhr, status, error) {
                showNotice('Export failed. Please try again.', 'error');
                console.error('Export error:', error);
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    /**
     * Add date range presets
     */
    function addDatePresets() {
        var $filterRow = $('.filter-row').last();
        var $presetsDiv = $('<div class="filter-row">');
        var $presetsLabel = $('<label>Date Presets:</label>');
        var $presetsSelect = $('<select id="date-presets">');
        
        var presets = [
            { label: 'Custom Range', value: 'custom' },
            { label: 'Last 7 Days', value: '7days' },
            { label: 'Last 30 Days', value: '30days' },
            { label: 'Last 90 Days', value: '90days' },
            { label: 'This Month', value: 'this-month' },
            { label: 'Last Month', value: 'last-month' },
            { label: 'This Year', value: 'this-year' }
        ];
        
        presets.forEach(function(preset) {
            $presetsSelect.append($('<option>', {
                value: preset.value,
                text: preset.label
            }));
        });
        
        $presetsDiv.append($presetsLabel).append($presetsSelect);
        $filterRow.after($presetsDiv);
        
        // Handle preset changes
        $presetsSelect.on('change', function() {
            var preset = $(this).val();
            var startDate = '';
            var endDate = new Date().toISOString().split('T')[0];
            
            switch(preset) {
                case '7days':
                    startDate = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    break;
                case '30days':
                    startDate = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    break;
                case '90days':
                    startDate = new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                    break;
                case 'this-month':
                    startDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
                    break;
                case 'last-month':
                    var lastMonth = new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1);
                    startDate = lastMonth.toISOString().split('T')[0];
                    endDate = new Date(new Date().getFullYear(), new Date().getMonth(), 0).toISOString().split('T')[0];
                    break;
                case 'this-year':
                    startDate = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0];
                    break;
                default:
                    return; // Custom range - don't change dates
            }
            
            $('#start_date').val(startDate);
            $('#end_date').val(endDate);
        });
    }
    
    /**
     * Show notice messages
     */
    function showNotice(message, type) {
        var $notice = $('<div class="gf-reports-notice ' + type + '">' + message + '</div>');
        $('.wrap h1').after($notice);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Add keyboard shortcuts
     */
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            e.preventDefault();
            $('.gf-reports-form').submit();
        }
        
        // Ctrl/Cmd + E to export CSV
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 69) {
            e.preventDefault();
            if ($('#export-csv').length) {
                $('#export-csv').click();
            }
        }
    });
    
    /**
     * Add tooltips for better UX
     */
    $('[title]').tooltip({
        position: { my: 'left+5 center', at: 'right center' }
    });
    
    /**
     * Responsive table handling
     */
    function handleResponsiveTable() {
        var $table = $('.recent-entries table');
        var $container = $('.recent-entries');
        
        if ($table.width() > $container.width()) {
            $container.addClass('table-scroll');
        } else {
            $container.removeClass('table-scroll');
        }
    }
    
    // Handle responsive table on load and resize
    handleResponsiveTable();
    $(window).on('resize', handleResponsiveTable);
    
    /**
     * Add loading states
     */
    $('.gf-reports-form').on('submit', function() {
        $('.gf-reports-results').addClass('gf-reports-loading');
    });
    
    // Remove loading state when page loads
    $(window).on('load', function() {
        $('.gf-reports-results').removeClass('gf-reports-loading');
    });
    
}); 