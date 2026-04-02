/**
 * Attribution Analytics JavaScript
 * 
 * Handles attribution dashboard interactions, charts, and data management
 */

(function($) {
    'use strict';
    
    // Attribution Dashboard Class
    window.GRAttribution = {
        charts: {
            entries: null,
            revenue: null,
            trends: null
        },
        
        currentData: null,
        
        /**
         * Initialize attribution dashboard
         */
        init: function() {
            this.bindEvents();
            this.initializeCharts();
            this.loadInitialData();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;
            
            // Update dashboard button
            $('#update-attribution-dashboard').on('click', function() {
                self.updateDashboard();
            });
            
            // Advanced filters toggle
            $('#toggle-advanced-filters').on('click', function() {
                $('.advanced-filters').slideToggle();
                var text = $(this).text();
                $(this).text(text === 'Advanced Filters' ? 'Hide Filters' : 'Advanced Filters');
            });
            
            // Chart type switching
            $('.chart-type-btn').on('click', function() {
                var chartContainer = $(this).closest('.chart-container');
                var chartType = $(this).data('type');
                var chartCanvas = chartContainer.find('canvas');
                
                // Update button states
                chartContainer.find('.chart-type-btn').removeClass('active');
                $(this).addClass('active');
                
                // Update chart
                self.updateChartType(chartCanvas.attr('id'), chartType);
            });
            
            // Filter change handlers
            $('#attribution-form-select, #attribution-group-by').on('change', function() {
                self.updateFilterOptions();
            });
            
            // Export buttons
            $('#export-attribution-csv').on('click', function() {
                self.exportData('csv');
            });
            
            $('#export-attribution-pdf').on('click', function() {
                self.exportData('pdf');
            });
        },
        
        /**
         * Initialize charts
         */
        initializeCharts: function() {
            this.initializeEntriesChart();
            this.initializeRevenueChart();
            this.initializeTrendsChart();
        },
        
        /**
         * Initialize entries chart
         */
        initializeEntriesChart: function() {
            var ctx = document.getElementById('attribution-entries-chart');
            if (!ctx) return;
            
            this.charts.entries = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.raw || 0;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize revenue chart
         */
        initializeRevenueChart: function() {
            var ctx = document.getElementById('attribution-revenue-chart');
            if (!ctx) return;
            
            this.charts.revenue = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: [
                            '#28a745', '#007bff', '#ffc107', '#17a2b8',
                            '#6f42c1', '#fd7e14', '#e83e8c', '#6c757d'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    var label = context.label || '';
                                    var value = context.raw || 0;
                                    var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return label + ': $' + value.toFixed(2) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },
        
        /**
         * Initialize trends chart (for date grouping)
         */
        initializeTrendsChart: function() {
            var ctx = document.getElementById('attribution-trends-chart');
            if (!ctx) return;
            
            this.charts.trends = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [
                        {
                            label: 'Entries',
                            data: [],
                            borderColor: '#36A2EB',
                            backgroundColor: 'rgba(54, 162, 235, 0.1)',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Revenue',
                            data: [],
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Entries'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Revenue ($)'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        },
        
        /**
         * Load initial data
         */
        loadInitialData: function() {
            if (typeof window.grAttributionData !== 'undefined') {
                this.updateChartsWithData(window.grAttributionData.chartData);
            } else {
                this.updateDashboard();
            }
        },
        
        /**
         * Update dashboard with current filter settings
         */
        updateDashboard: function() {
            var self = this;
            var formId = $('#attribution-form-select').val();
            var groupBy = $('#attribution-group-by').val();
            var startDate = $('#attribution-start-date').val();
            var endDate = $('#attribution-end-date').val();
            
            // Show loading state
            this.showLoading(true);
            
            $.ajax({
                url: gr_quickreports_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gr_get_attribution_data',
                    nonce: gr_quickreports_ajax.nonce,
                    form_id: formId,
                    group_by: groupBy,
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    if (response.success) {
                        self.updateChartsWithData(response.data);
                        self.updateSummaryCards(response.data);
                        self.updateDataTable(response.data, groupBy);
                    } else {
                        console.error('Failed to load attribution data:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                },
                complete: function() {
                    self.showLoading(false);
                }
            });
        },
        
        /**
         * Update charts with new data
         */
        updateChartsWithData: function(data) {
            var groupBy = $('#attribution-group-by').val() || 'channel';
            
            if (groupBy === 'date') {
                this.updateTrendsChart(data);
                this.hidePieCharts();
            } else {
                this.updatePieCharts(data, groupBy);
                this.hideTrendsChart();
            }
        },
        
        /**
         * Update pie charts
         */
        updatePieCharts: function(data, groupBy) {
            var labels = [];
            var entriesData = [];
            var revenueData = [];
            
            data.forEach(function(item) {
                var name = item.group_name || item.date_group || '(not set)';
                labels.push(name);
                entriesData.push(parseInt(item.entries) || 0);
                revenueData.push(parseFloat(item.total_revenue) || 0);
            });
            
            // Update entries chart
            if (this.charts.entries) {
                this.charts.entries.data.labels = labels;
                this.charts.entries.data.datasets[0].data = entriesData;
                this.charts.entries.update();
            }
            
            // Update revenue chart
            if (this.charts.revenue) {
                this.charts.revenue.data.labels = labels;
                this.charts.revenue.data.datasets[0].data = revenueData;
                this.charts.revenue.update();
            }
            
            // Show pie chart containers
            $('#attribution-entries-chart').closest('.chart-container').show();
            $('#attribution-revenue-chart').closest('.chart-container').show();
        },
        
        /**
         * Update trends chart for date grouping
         */
        updateTrendsChart: function(data) {
            if (!this.charts.trends) return;
            
            var labels = [];
            var entriesData = [];
            var revenueData = [];
            
            data.forEach(function(item) {
                labels.push(item.date_group);
                entriesData.push(parseInt(item.entries) || 0);
                revenueData.push(parseFloat(item.total_revenue) || 0);
            });
            
            this.charts.trends.data.labels = labels;
            this.charts.trends.data.datasets[0].data = entriesData;
            this.charts.trends.data.datasets[1].data = revenueData;
            this.charts.trends.update();
            
            // Show trends chart container
            $('#attribution-trends-chart').closest('.chart-container').show();
        },
        
        /**
         * Hide pie charts
         */
        hidePieCharts: function() {
            $('#attribution-entries-chart').closest('.chart-container').hide();
            $('#attribution-revenue-chart').closest('.chart-container').hide();
        },
        
        /**
         * Hide trends chart
         */
        hideTrendsChart: function() {
            $('#attribution-trends-chart').closest('.chart-container').hide();
        },
        
        /**
         * Update chart type (pie/bar)
         */
        updateChartType: function(chartId, type) {
            var chart = null;
            
            if (chartId === 'attribution-entries-chart') {
                chart = this.charts.entries;
            } else if (chartId === 'attribution-revenue-chart') {
                chart = this.charts.revenue;
            }
            
            if (!chart) return;
            
            // Store current data
            var currentData = {
                labels: chart.data.labels,
                data: chart.data.datasets[0].data
            };
            
            // Destroy and recreate chart with new type
            chart.destroy();
            
            var ctx = document.getElementById(chartId);
            var newChart = new Chart(ctx, {
                type: type,
                data: {
                    labels: currentData.labels,
                    datasets: [{
                        data: currentData.data,
                        backgroundColor: chart.data.datasets[0].backgroundColor,
                        borderWidth: 1
                    }]
                },
                options: this.getChartOptions(type, chartId)
            });
            
            // Update reference
            if (chartId === 'attribution-entries-chart') {
                this.charts.entries = newChart;
            } else if (chartId === 'attribution-revenue-chart') {
                this.charts.revenue = newChart;
            }
        },
        
        /**
         * Get chart options based on type
         */
        getChartOptions: function(type, chartId) {
            var baseOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: type === 'pie' ? 'bottom' : 'top'
                    }
                }
            };
            
            if (type === 'bar') {
                baseOptions.scales = {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: chartId.includes('revenue') ? 'Revenue ($)' : 'Entries'
                        }
                    }
                };
            }
            
            return baseOptions;
        },
        
        /**
         * Update summary cards
         */
        updateSummaryCards: function(data) {
            var totalEntries = 0;
            var totalRevenue = 0;
            var uniqueChannels = new Set();
            
            data.forEach(function(item) {
                totalEntries += parseInt(item.entries) || 0;
                totalRevenue += parseFloat(item.total_revenue) || 0;
                if (item.group_name) {
                    uniqueChannels.add(item.group_name);
                }
            });
            
            var avgRevenuePerEntry = totalEntries > 0 ? totalRevenue / totalEntries : 0;
            
            // Update card values
            $('.summary-card').eq(0).find('.card-value').text(totalEntries.toLocaleString());
            $('.summary-card').eq(1).find('.card-value').text('$' + totalRevenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('.summary-card').eq(2).find('.card-value').text('$' + avgRevenuePerEntry.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
            $('.summary-card').eq(3).find('.card-value').text(uniqueChannels.size);
        },
        
        /**
         * Update data table
         */
        updateDataTable: function(data, groupBy) {
            var tbody = $('#attribution-data-table tbody');
            tbody.empty();
            
            if (data.length === 0) {
                tbody.append('<tr><td colspan="7" class="no-data">No attribution data found for the selected criteria.</td></tr>');
                return;
            }
            
            data.forEach(function(item) {
                var groupName = item.group_name || item.date_group || '(not set)';
                var entries = parseInt(item.entries) || 0;
                var revenue = parseFloat(item.total_revenue) || 0;
                var avgRevenue = entries > 0 ? revenue / entries : 0;
                
                // Calculate cost and ROI (placeholder)
                var cost = 0;
                var roi = 0;
                var conversionRate = '—';
                
                var row = '<tr>' +
                    '<td><strong>' + groupName + '</strong></td>' +
                    '<td>' + entries.toLocaleString() + '</td>' +
                    '<td>$' + revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td>$' + avgRevenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td>$' + cost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>' +
                    '<td><span class="roi-neutral">' + roi.toFixed(1) + '%</span></td>' +
                    '<td><span class="conversion-unknown">' + conversionRate + '</span></td>' +
                    '</tr>';
                
                tbody.append(row);
            });
        },
        
        /**
         * Update filter options based on current selections
         */
        updateFilterOptions: function() {
            var self = this;
            var formId = $('#attribution-form-select').val();
            var startDate = $('#attribution-start-date').val();
            var endDate = $('#attribution-end-date').val();
            
            $.ajax({
                url: gr_quickreports_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gr_get_attribution_filters',
                    nonce: gr_quickreports_ajax.nonce,
                    form_id: formId,
                    start_date: startDate,
                    end_date: endDate
                },
                success: function(response) {
                    if (response.success) {
                        self.populateFilterDropdowns(response.data);
                    }
                }
            });
        },
        
        /**
         * Populate filter dropdowns
         */
        populateFilterDropdowns: function(filters) {
            // Update channel filter
            var channelSelect = $('#attribution-channel-filter');
            channelSelect.find('option:not(:first)').remove();
            filters.channels.forEach(function(channel) {
                channelSelect.append('<option value="' + channel.value + '">' + channel.value + ' (' + channel.count + ')</option>');
            });
            
            // Update source filter
            var sourceSelect = $('#attribution-source-filter');
            sourceSelect.find('option:not(:first)').remove();
            filters.sources.forEach(function(source) {
                sourceSelect.append('<option value="' + source.value + '">' + source.value + ' (' + source.count + ')</option>');
            });
            
            // Update campaign filter
            var campaignSelect = $('#attribution-campaign-filter');
            campaignSelect.find('option:not(:first)').remove();
            filters.campaigns.forEach(function(campaign) {
                campaignSelect.append('<option value="' + campaign.value + '">' + campaign.value + ' (' + campaign.count + ')</option>');
            });
        },
        
        /**
         * Export attribution data
         */
        exportData: function(format) {
            var formData = new FormData();
            formData.append('action', 'gr_export_attribution_' + format);
            formData.append('nonce', gr_quickreports_ajax.nonce);
            formData.append('form_id', $('#attribution-form-select').val());
            formData.append('group_by', $('#attribution-group-by').val());
            formData.append('start_date', $('#attribution-start-date').val());
            formData.append('end_date', $('#attribution-end-date').val());
            
            // Add chart images for PDF export
            if (format === 'pdf') {
                if (this.charts.entries) {
                    formData.append('entries_chart', this.charts.entries.toBase64Image());
                }
                if (this.charts.revenue) {
                    formData.append('revenue_chart', this.charts.revenue.toBase64Image());
                }
            }
            
            // Create and submit form
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = gr_quickreports_ajax.ajax_url;
            form.style.display = 'none';
            
            // Convert FormData to form inputs
            for (var pair of formData.entries()) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = pair[0];
                input.value = pair[1];
                form.appendChild(input);
            }
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        },
        
        /**
         * Show/hide loading state
         */
        showLoading: function(show) {
            if (show) {
                $('.gr-attribution-dashboard').addClass('loading');
                $('#update-attribution-dashboard').prop('disabled', true).text('Loading...');
            } else {
                $('.gr-attribution-dashboard').removeClass('loading');
                $('#update-attribution-dashboard').prop('disabled', false).text('Update Dashboard');
            }
        }
    };
    
    // Initialize when attribution tab is shown
    $(document).on('click', '#attribution-tab-link', function() {
        setTimeout(function() {
            if (!window.GRAttribution.charts.entries) {
                window.GRAttribution.init();
            }
        }, 100);
    });
    
    // Initialize if already on attribution tab
    $(document).ready(function() {
        if ($('#attribution-tab-content').hasClass('active')) {
            window.GRAttribution.init();
        }
    });
    
})(jQuery);
