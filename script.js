/* DOKUWIKI:include_once flot/jquery.flot.min.js */
/* DOKUWIKI:include_once flot/jquery.flot.pie.min.js */
/* DOKUWIKI:include_once flot/excanvas.min.js */

jQuery(function() {
    jQuery('.stratachart_pie').each(function(_,e) {
        var $chart = jQuery(e);
        var data = jQuery.parseJSON($chart.attr('data-pie'));
        var significance = $chart.attr('data-significance');
        var legend = $chart.attr('data-legend');
        jQuery.plot($chart, data, {
            series: { 
                pie: {
                    show: true
                }
            },
            legend: {
                show: (legend=='1'),
                labelFormatter: function(label, series) {
                    return label + ' (' + series.data[0][1].toFixed(significance) + ')';
                }
            }
        });
    });

});

