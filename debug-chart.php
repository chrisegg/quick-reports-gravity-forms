<?php
/*
 * Debug script for GF Reports Chart Issues
 * Add this to wp-config.php: define('WP_DEBUG', true); define('WP_DEBUG_LOG', true);
 */

// Hook to add debug info to admin footer
add_action('admin_footer', 'gf_reports_debug_chart_info');

function gf_reports_debug_chart_info() {
    // Only show on our reports page
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'gravity-forms_page_gf-reports') {
        return;
    }
    
    ?>
    <script>
    // Debug Chart.js and data availability
    jQuery(document).ready(function($) {
        console.log('=== GF Reports Chart Debug Info ===');
        console.log('Current page ID:', '<?php echo $screen->id; ?>');
        console.log('Chart.js available:', typeof Chart !== 'undefined');
        console.log('jQuery available:', typeof $ !== 'undefined');
        console.log('Canvas element:', $('#entriesChart').length ? 'Found' : 'Not found');
        console.log('Chart data available:', typeof window.chartData !== 'undefined');
        console.log('Chart mode available:', typeof window.chartMode !== 'undefined');
        
        if (typeof window.chartData !== 'undefined') {
            console.log('Chart data:', window.chartData);
        }
        
        if (typeof window.chartMode !== 'undefined') {
            console.log('Chart mode:', window.chartMode);
        }
        
        if (typeof window.compareChartData !== 'undefined') {
            console.log('Compare data:', window.compareChartData);
        }
        
        // Check if canvas can be accessed
        var canvas = document.getElementById('entriesChart');
        if (canvas) {
            console.log('Canvas dimensions:', canvas.width, 'x', canvas.height);
            console.log('Canvas style:', window.getComputedStyle(canvas));
            
            try {
                var ctx = canvas.getContext('2d');
                console.log('Canvas context available:', !!ctx);
            } catch (e) {
                console.error('Canvas context error:', e);
            }
        }
        
        // Test Chart.js manually
        if (typeof Chart !== 'undefined' && $('#entriesChart').length) {
            console.log('Attempting manual chart creation...');
            try {
                var testChart = new Chart($('#entriesChart')[0].getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: ['Test 1', 'Test 2'],
                        datasets: [{
                            label: 'Test Dataset',
                            data: [1, 2],
                            borderColor: 'red'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                console.log('Manual chart creation successful:', testChart);
                
                // Destroy test chart after 2 seconds
                setTimeout(function() {
                    testChart.destroy();
                    console.log('Test chart destroyed');
                }, 2000);
                
            } catch (e) {
                console.error('Manual chart creation failed:', e);
            }
        }
        
        console.log('=== End Debug Info ===');
    });
    </script>
    <?php
}
?> 