/**
 * Gravity Forms Quick Reports Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Flag to track if comparison dropdown is being populated
    var isPopulatingCompareDropdown = false;
    
    // Initialize chart if data is available
    if (typeof window.chartData !== 'undefined') {
        initializeChart();
    }
    
    // Initialize revenue chart if data is available
    if (typeof window.revenueChartData !== 'undefined') {
        initializeRevenueChart();
    }
    
    // Export functionality
    $('#export-csv, #export-pdf').on('click', function(e) {
        e.preventDefault();
        var exportType = $(this).attr('id').replace('export-', '');
        exportReport(exportType);
    });
    
    // Form validation and submission handling
    $('#gf-quickreports-form').on('submit', function(e) {
        e.preventDefault();
        
        var formId = $('#form_id').val();
        var preset = $('#date_preset').val();
        var compareFormId = $('#compare_form_id').val();
        var isPopulatingCompareDropdown = $('#compare_form_id').data('populating');
        
        // Check form selection
        if (!formId) {
            showNotice('Please select a form to generate a report.', 'error');
            return false;
        }
        
        // If no comparison form ID from dropdown, try to get it from URL
        if (!compareFormId) {
            compareFormId = new URLSearchParams(window.location.search).get('compare_form_id');
        }
        
        // If comparison dropdown is still populating, wait for it to finish
        if (isPopulatingCompareDropdown) {
            var checkInterval = setInterval(function() {
                if (!$('#compare_form_id').data('populating')) {
                    clearInterval(checkInterval);
                    $('#gf-quickreports-form').submit();
                }
            }, 100);
            return;
        }
        
        // Preserve comparison form ID in URL if selected
        if (compareFormId) {
            var url = new URL(window.location);
            url.searchParams.set('compare_form_id', compareFormId);
            window.history.replaceState({}, '', url);
        } else {
            // Remove from URL if not selected
            var url = new URL(window.location);
            url.searchParams.delete('compare_form_id');
            window.history.replaceState({}, '', url);
        }
        
        // Handle date preset validation
        if (preset !== 'custom') {
            // For non-custom presets, we don't need to validate date fields
            // The server will handle the preset logic
            this.submit();
            return;
        }
        
        // For custom range, validate the date fields
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        
        if (!startDate || !endDate) {
            showNotice('Please select both start and end dates for custom range.', 'error');
            return false;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            showNotice('Start date cannot be after end date.', 'error');
            return false;
        }
        
        // Submit the form
        this.submit();
    });
    
    // Update compare form options on main form change
    $('#form_id').on('change', function() {
        var selectedForm = $(this).val();
        updateCompareFormOptions(selectedForm, null);
        updateChartViewVisibility(selectedForm);
    });

    // Initial state setup
    var initialFormId = $('#form_id').val();
    updateCompareFormOptions(initialFormId, $('#current_compare_form_id').val());
    updateChartViewVisibility(initialFormId);
    updateDateFields($('#date_preset').val());
    handleResponsiveTable();
    
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
            var labels = [];
            var hasData = false;
            var mode = typeof window.chartMode !== 'undefined' ? window.chartMode : 'per_day';
            var chartData = window.chartData;
            var compareChartData = window.compareChartData;
            var chartView = ($('#form_id').val() === 'all') ? ($('#chart_view').val() || 'individual') : 'aggregated';
            var mainLabel = typeof window.selectedFormLabel !== 'undefined' ? 
                formatFormLabel(window.selectedFormLabel) : 
                formatFormLabel($('#form_id option:selected').text() || 'Form 1');
            var compareLabel = formatFormLabel($('#compare_form_id option:selected').text() || 'Form 2');

            if (mode === 'total' && chartView === 'individual') {
                // Bar chart showing total entries for each individual form
                labels = window.individualFormsData.map(d => d.label);
                const dataValues = window.individualFormsData.map(d => d.data.reduce((a, b) => a + b, 0));
                datasets.push({
                    label: 'Total Entries',
                    data: dataValues,
                    backgroundColor: window.individualFormsData.map(d => d.backgroundColor),
                    borderColor: window.individualFormsData.map(d => d.borderColor),
                    borderWidth: 1
                });
                hasData = dataValues.some(v => v > 0);
            } else if (chartView === 'individual') {
                // Line chart showing individual forms over time
                labels = chartData.labels || [];
                datasets = window.individualFormsData;
                hasData = datasets.some(d => d.data.some(v => v > 0));
            } else {
                // Default aggregated view (line or bar)
                labels = (mode === 'total') ? [mainLabel] : (chartData.labels || []);
                const mainDataset = {
                    label: mainLabel,
                    data: (mode === 'total') ? [chartData.data.reduce((a, b) => a + b, 0)] : chartData.data,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                };
                datasets.push(mainDataset);
                hasData = mainDataset.data.some(v => v > 0);
            }
            
            // Add comparison data if available and not in individual view
            if (compareChartData && compareChartData.data && compareChartData.data.length > 0 && chartView !== 'individual') {
                hasData = true;
                datasets.push({
                    label: compareLabel,
                    data: (mode === 'total') ? [compareChartData.data.reduce((a, b) => a + b, 0)] : compareChartData.data,
                    borderColor: '#d63638',
                    backgroundColor: 'rgba(214, 54, 56, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
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
                            position: 'top',
                            labels: {
                                boxWidth: 12,
                                padding: 20
                            }
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
     * Initialize Chart.js for revenue over time
     */
    function initializeRevenueChart() {
        var canvas = document.getElementById('revenueChart');
        if (!canvas) {
            return;
        }
        
        console.log('Initializing revenue chart with data:', window.revenueChartData);
        
        try {
            var ctx = canvas.getContext('2d');
            var datasets = [];
            var hasData = false;
            var mode = typeof window.chartMode !== 'undefined' ? window.chartMode : 'per_day';
            var chartData = window.revenueChartData;
            var compareChartData = window.compareRevenueChartData;
            var chartView = ($('#form_id').val() === 'all') ? ($('#chart_view').val() || 'individual') : 'aggregated';

            console.log('Revenue chart data values:', chartData ? chartData.data : 'no data');
            console.log('Revenue chart data type check:', chartData ? chartData.data.some(function(v){return parseFloat(v) > 0;}) : 'no data');

            // Refactored logic to handle different chart views
            var mainLabel = typeof window.selectedFormLabel !== 'undefined' ? formatFormLabel(window.selectedFormLabel) : 'Revenue';
            var compareLabel = formatFormLabel($('#compare_form_id option:selected').text() || 'Compare Revenue');
            var datasets = [];
            var labels = [];
            var hasData = false;

            if (mode === 'total' && chartView === 'individual') {
                // Bar chart showing total revenue for each individual form
                labels = window.individualRevenueData.map(d => d.label);
                const dataValues = window.individualRevenueData.map(d => d.data.reduce((a, b) => a + b, 0));
                datasets.push({
                    label: 'Total Revenue',
                    data: dataValues,
                    backgroundColor: window.individualRevenueData.map(d => d.backgroundColor),
                    borderColor: window.individualRevenueData.map(d => d.borderColor),
                    borderWidth: 1
                });
                hasData = dataValues.some(v => v > 0);
            } else if (chartView === 'individual') {
                // Line chart showing individual forms' revenue over time
                labels = chartData.labels || [];
                datasets = window.individualRevenueData;
                hasData = datasets.some(d => d.data.some(v => v > 0));
            } else {
                // Default aggregated view (line or bar)
                if (chartData && chartData.data && chartData.data.some(v => v > 0)) {
                    labels = (mode === 'total') ? [mainLabel] : (chartData.labels || []);
                    const mainDataset = {
                        label: mainLabel + ' (Revenue)',
                        data: (mode === 'total') ? [chartData.data.reduce((a, b) => a + b, 0)] : chartData.data,
                        borderColor: '#ff9500',
                        backgroundColor: 'rgba(255, 149, 0, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    };
                    datasets.push(mainDataset);
                    hasData = mainDataset.data.some(v => v > 0);
                }
            }

            // Add comparison data if available and not in individual view
            if (compareChartData && compareChartData.data && compareChartData.data.length > 0 && chartView !== 'individual') {
                if (compareChartData.data.some(v => v > 0)) {
                    hasData = true;
                    datasets.push({
                        label: compareLabel + ' (Revenue)',
                        data: (mode === 'total') ? [compareChartData.data.reduce((a, b) => a + b, 0)] : compareChartData.data,
                        borderColor: '#ff3b30',
                        backgroundColor: 'rgba(255, 59, 48, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    });
                }
            }

            // No data
            if (!hasData) {
                $('#revenueChart').hide();
                $('#revenue-chartjs-no-data').show();
                return;
            } else {
                $('#revenueChart').show();
                $('#revenue-chartjs-no-data').hide();
            }
            
            // Chart type and labels
            var chartType = (mode === 'total') ? 'bar' : 'line';
            var labels = (mode === 'total') ? ['Total'] : (chartData.labels || []);
            
            window.currentRevenueChart = new Chart(ctx, {
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
                            borderColor: '#ff9500',
                            borderWidth: 1,
                            cornerRadius: 4,
                            displayColors: true,
                            callbacks: {
                                title: function(context) {
                                    return mode === 'total' ? 'Total' : 'Date: ' + context[0].label;
                                },
                                label: function(context) {
                                    return 'Revenue: $' + context.parsed.y.toLocaleString();
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
                                    return '$' + value.toLocaleString();
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
                            hoverBackgroundColor: '#ff9500'
                        }
                    }
                }
            });
            
        } catch (error) {
            $('#revenueChart').hide();
            $('#revenue-chartjs-no-data').text('Error creating revenue chart: ' + error.message).show();
        }
    }
    
    /**
     * Export report as CSV or PDF
     */
    function exportReport(type) {
        var formId = $('#form_id').val();
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        var compareFormId = $('#compare_form_id').val();
        
        // If no comparison form ID from dropdown, try to get it from URL
        if (!compareFormId) {
            compareFormId = new URLSearchParams(window.location.search).get('compare_form_id');
        }
        
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
        
        // Add comparison form ID if selected
        if (compareFormId) {
            formData.append('compare_form_id', compareFormId);
        }

        // For PDF export, include the charts as images
        if (type === 'pdf') {
            try {
                var chartCanvas = document.getElementById('entriesChart');
                var revenueChartCanvas = document.getElementById('revenueChart');
                
                if (chartCanvas && window.currentChart) {
                    var chartImage = chartCanvas.toDataURL('image/png');
                    formData.append('chart_data', chartImage);
                }
                
                if (revenueChartCanvas && window.currentRevenueChart) {
                    var revenueChartImage = revenueChartCanvas.toDataURL('image/png');
                    formData.append('revenue_chart_data', revenueChartImage);
                }
            } catch (e) {
                showNotice('Error capturing charts for PDF. Please try again.', 'error');
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
        var currentCompareFormId = $('#current_compare_form_id').val();
        var existingCompareFormId = $('#compare_form_id').val();
        
        if (selectedForm && selectedForm !== 'all') {
            // If a form is selected and there's a comparison form already selected, populate the dropdown
            if (currentCompareFormId || existingCompareFormId) {
                var compareFormIdToUse = currentCompareFormId || existingCompareFormId;
                updateCompareFormOptions(selectedForm, compareFormIdToUse);
            } else {
                updateCompareFormOptions(selectedForm);
            }
        } else {
            // Disable compare form dropdown for "All Forms" or no selection
            $('#compare_form_id').html('<option value="">Compare With...</option>').prop('disabled', true);
        }
        
        updateChartViewVisibility(selectedForm);
        
        // Initialize date preset and fields on page load
        var selectedPreset = $('#date_preset').val();
        if (selectedPreset && selectedPreset !== 'custom') {
            // If a preset is selected, hide date fields and update them
            hideDateFields();
            updateDateFieldsFromPreset(selectedPreset);
        } else {
            showDateFields();
        }
    }
    
    /**
     * Update date fields from preset without AJAX (for initialization)
     */
    function updateDateFieldsFromPreset(preset) {
        var startDate = '';
        var endDate = '';
        
        switch(preset) {
            case 'today':
                startDate = new Date().toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case 'yesterday':
                startDate = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                endDate = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                break;
            case '7days':
                startDate = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case '30days':
                startDate = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case '60days':
                startDate = new Date(Date.now() - 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case '90days':
                startDate = new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case 'year_to_date':
                startDate = new Date(new Date().getFullYear(), 0, 1).toISOString().split('T')[0];
                endDate = new Date().toISOString().split('T')[0];
                break;
            case 'last_year':
                startDate = new Date(new Date().getFullYear() - 1, 0, 1).toISOString().split('T')[0];
                endDate = new Date(new Date().getFullYear() - 1, 11, 31).toISOString().split('T')[0];
                break;
        }
        
        if (startDate && endDate) {
            $('#start_date').val(startDate);
            $('#end_date').val(endDate);
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
        if (window.currentRevenueChart) {
            window.currentRevenueChart.destroy();
            window.currentRevenueChart = null;
        }
        initializeChart();
        initializeRevenueChart();
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
    function updateCompareFormOptions(selectedForm, preserveValue) {
        if (!selectedForm || selectedForm === 'all') {
            // Disable compare form dropdown for "All Forms" or no selection
            $('#compare_form_id').html('<option value="">Compare With...</option>').prop('disabled', true);
            return;
        }
        
        isPopulatingCompareDropdown = true;
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
                        var $option = $('<option>', {
                            value: option.value,
                            text: option.label
                        });
                        
                        // Preserve the selected value if provided
                        if (preserveValue && option.value == preserveValue) {
                            $option.prop('selected', true);
                        }
                        
                        $select.append($option);
                    });
                    
                    $select.prop('disabled', false);
                } else {
                    console.error('AJAX response indicates failure:', response);
                }
                isPopulatingCompareDropdown = false;
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                console.error('Response:', xhr.responseText);
                showNotice('Error loading compare form options.', 'error');
                isPopulatingCompareDropdown = false;
            }
        });
    }
    
    /**
     * Shows/hides the chart view dropdown based on form selection
     */
    function updateChartViewVisibility(selectedForm) {
        var $chartViewContainer = $('#chart-view-container');
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