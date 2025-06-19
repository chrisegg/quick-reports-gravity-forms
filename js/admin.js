/**
 * Gravity Forms Quick Reports Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Initialize chart if data is available
    if (typeof window.chartData !== 'undefined') {
        initializeChart();
    }
    
    // Export functionality
    $('#export-csv, #export-pdf').on('click', function(e) {
        e.preventDefault();
        var exportType = $(this).attr('id').replace('export-', '');
        exportReport(exportType);
    });
    
    // Form validation
    $('.gf-quickreports-form').on('submit', function(e) {
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
    
    // Form selection change - update compare form options
    $('#form_id').on('change', function() {
        var selectedForm = $(this).val();
        updateCompareFormOptions(selectedForm);
        updateChartViewVisibility(selectedForm);
    });
    
    // Date preset change
    $('#date_preset').on('change', function() {
        var preset = $(this).val();
        if (preset !== 'custom') {
            updateDateFields(preset);
        } else {
            showDateFields();
        }
    });
    
    // Date preset functionality
    addDatePresets();
    
    /**
     * Initialize Chart.js for entries over time
     */
    function initializeChart() {
        var canvas = document.getElementById('entriesChart');
        if (!canvas) {
            return;
        }
        
        try {
            var ctx = canvas.getContext('2d');
            var datasets = [];
            var hasData = false;
            var mode = typeof window.chartMode !== 'undefined' ? window.chartMode : 'per_day';
            var chartData = window.chartData;
            var compareChartData = window.compareChartData;

            // Main form dataset
            if (chartData && chartData.data && chartData.data.length > 0 && chartData.data.some(function(v){return v>0;})) {
                hasData = true;
                var formLabel = typeof window.selectedFormLabel !== 'undefined' ? 
                    formatFormLabel(window.selectedFormLabel) : 
                    formatFormLabel($('#form_id option:selected').text() || 'Form 1');
                
                datasets.push({
                    label: formLabel,
                    data: mode === 'total' ? [chartData.data.reduce((a,b)=>a+b,0)] : chartData.data,
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
                });
            }
            
            // Individual forms datasets (for "All Forms" view)
            if (typeof window.individualFormsData !== 'undefined' && window.individualFormsData.length > 0 && 
                typeof window.chartView !== 'undefined' && window.chartView === 'individual') {
                hasData = true;
                
                // Add each individual form as a separate dataset
                window.individualFormsData.forEach(function(formDataset) {
                    datasets.push(formDataset);
                });
                
                // Remove the aggregated "All Forms" dataset since we're showing individual forms
                datasets = datasets.filter(function(dataset) {
                    return dataset.label !== 'All Forms';
                });
            }
            
            // Compare form dataset
            if (compareChartData && compareChartData.data && compareChartData.data.length > 0 && compareChartData.data.some(function(v){return v>0;})) {
                hasData = true;
                var compareLabel = formatFormLabel($('#compare_form_id option:selected').text() || 'Form 2');
                
                datasets.push({
                    label: compareLabel,
                    data: mode === 'total' ? [compareChartData.data.reduce((a,b)=>a+b,0)] : compareChartData.data,
                    borderColor: '#34c759',
                    backgroundColor: 'rgba(52, 199, 89, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#34c759',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                });
            }
            
            // No data
            if (!hasData) {
                $('#entriesChart').hide();
                $('#chartjs-no-data').show();
                return;
            } else {
                $('#entriesChart').show();
                $('#chartjs-no-data').hide();
            }
            
            // Chart type and labels
            var chartType = (mode === 'total') ? 'bar' : 'line';
            var labels = (mode === 'total') ? ['Total'] : (chartData.labels || []);
            
            window.currentChart = new Chart(ctx, {
                type: chartType,
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#2271b1',
                            borderWidth: 1,
                            cornerRadius: 4,
                            displayColors: true,
                            callbacks: {
                                title: function(context) {
                                    return mode === 'total' ? 'Total' : 'Date: ' + context[0].label;
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
            
        } catch (error) {
            $('#entriesChart').hide();
            $('#chartjs-no-data').text('Error creating chart: ' + error.message).show();
        }
    }
    
    /**
     * Export report as CSV or PDF
     */
    function exportReport(type) {
        var formId = $('#form_id').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (!formId) {
            showNotice('Please select a form to export.', 'error');
            return;
        }
        
        // Show loading state
        var $button = $('#export-' + type);
        var originalText = $button.text();
        $button.text('Exporting...').prop('disabled', true);
        
        // Create form data for AJAX
        var formData = new FormData();
        formData.append('action', 'gf_quickreports_export_' + type);
        formData.append('nonce', gf_quickreports_ajax.nonce);
        formData.append('form_id', formId);
        formData.append('start_date', startDate);
        formData.append('end_date', endDate);

        // For PDF export, include the chart as an image
        if (type === 'pdf' && window.currentChart) {
            try {
                var chartCanvas = document.getElementById('entriesChart');
                var chartImage = chartCanvas.toDataURL('image/png');
                formData.append('chart_data', chartImage);
            } catch (e) {
                showNotice('Error capturing chart for PDF. Please try again.', 'error');
                $button.text(originalText).prop('disabled', false);
                return;
            }
        }
        
        // Make AJAX request
        $.ajax({
            url: gf_quickreports_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.responseType = type === 'pdf' ? 'arraybuffer' : 'blob';
                return xhr;
            },
            success: function(response) {
                try {
                    // Create blob from response
                    var blob;
                    if (type === 'pdf') {
                        blob = new Blob([response], { type: 'application/pdf' });
                    } else {
                        blob = new Blob([response], { type: 'text/csv;charset=utf-8' });
                    }
                    
                    // Create and trigger download
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'gf-quickreports-' + formId + '-' + new Date().toISOString().split('T')[0] + '.' + type;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    showNotice(type.toUpperCase() + ' export completed successfully!', 'success');
                } catch (e) {
                    showNotice('Error processing ' + type.toUpperCase() + ' export response.', 'error');
                }
            },
            error: function() {
                showNotice('Export failed. Please try again.', 'error');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    }
    
    /**
     * Format form label for chart display
     */
    function formatFormLabel(label) {
        return label.replace(/\s+/g, ' ').trim();
    }
    
    /**
     * Add date range presets
     */
    function addDatePresets() {
        // Initialize compare form options on page load
        var selectedForm = $('#form_id').val();
        if (selectedForm) {
            updateCompareFormOptions(selectedForm);
            updateChartViewVisibility(selectedForm);
        }
        
        // Initialize date preset if one is selected
        var selectedPreset = $('#date_preset').val();
        if (selectedPreset && selectedPreset !== 'custom') {
            hideDateFields();
        } else {
            showDateFields();
        }
    }
    
    /**
     * Show notice messages
     */
    function showNotice(message, type) {
        var $notice = $('<div class="gf-quickreports-notice ' + type + '">' + message + '</div>');
        $('.wrap h1').after($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Handle chart view change for "All Forms"
    $('#chart_view').on('change', function() {
        window.chartView = $(this).val();
        if (window.currentChart) {
            window.currentChart.destroy();
            window.currentChart = null;
        }
        initializeChart();
    });
    
    // Add keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
            e.preventDefault();
            $('.gf-quickreports-form').submit();
        }
        
        // Ctrl/Cmd + E to export CSV
        if ((e.ctrlKey || e.metaKey) && e.keyCode === 69) {
            e.preventDefault();
            if ($('#export-csv').length) {
                $('#export-csv').click();
            }
        }
    });
    
    // Handle responsive table
    function handleResponsiveTable() {
        var $table = $('.recent-entries table');
        var $container = $('.recent-entries');
        
        if ($table.width() > $container.width()) {
            $container.addClass('table-scroll');
        } else {
            $container.removeClass('table-scroll');
        }
    }
    
    handleResponsiveTable();
    $(window).on('resize', handleResponsiveTable);
    
    /**
     * Update compare form options via AJAX
     */
    function updateCompareFormOptions(selectedForm) {
        if (!selectedForm || selectedForm === 'all') {
            $('#compare_form_id').html('<option value="">Compare With...</option>').prop('disabled', true);
            return;
        }
        
        $.ajax({
            url: gf_quickreports_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gf_quickreports_get_compare_forms',
                nonce: gf_quickreports_ajax.nonce,
                selected_form: selectedForm
            },
            success: function(response) {
                if (response.success) {
                    var $select = $('#compare_form_id');
                    $select.html('<option value="">Compare With...</option>');
                    
                    response.data.options.forEach(function(option) {
                        $select.append($('<option>', {
                            value: option.value,
                            text: option.label
                        }));
                    });
                    
                    $select.prop('disabled', false);
                }
            },
            error: function() {
                showNotice('Error loading compare form options.', 'error');
            }
        });
    }
    
    /**
     * Update chart view visibility based on form selection
     */
    function updateChartViewVisibility(selectedForm) {
        var $chartViewContainer = $('#chart_view').closest('.alignleft');
        if (selectedForm === 'all') {
            $chartViewContainer.show();
        } else {
            $chartViewContainer.hide();
        }
    }
    
    /**
     * Update date fields based on preset
     */
    function updateDateFields(preset) {
        $.ajax({
            url: gf_quickreports_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gf_quickreports_get_date_presets',
                nonce: gf_quickreports_ajax.nonce,
                preset: preset
            },
            success: function(response) {
                if (response.success) {
                    $('#start_date').val(response.data.start_date);
                    $('#end_date').val(response.data.end_date);
                    
                    if (preset === 'custom') {
                        showDateFields();
                    } else {
                        hideDateFields();
                    }
                }
            },
            error: function() {
                showNotice('Error loading date preset.', 'error');
            }
        });
    }
    
    /**
     * Show date input fields
     */
    function showDateFields() {
        $('.date-range-container').removeClass('hidden').show();
    }
    
    /**
     * Hide date input fields
     */
    function hideDateFields() {
        $('.date-range-container').addClass('hidden');
    }
}); 