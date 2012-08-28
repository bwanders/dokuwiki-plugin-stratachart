/* DOKUWIKI:include_once flot/jquery.flot.min.js */
/* DOKUWIKI:include_once flot/jquery.flot.pie.min.js */
/* DOKUWIKI:include_once flot/excanvas.min.js */

jQuery(function() {
    jQuery('.stratachart_pie').each(function(_,e) {
        var g = function(x){console.log(x); return x;}

        var $chart = jQuery(e);
        var data = jQuery.parseJSON($chart.attr('data-pie'));
        var options = jQuery.parseJSON($chart.attr('data-options'));
        jQuery.plot($chart, data, g({
            series: { 
                pie: {
                    show: true,
                    stroke: {
                        color: options.strokeColor
                    },
                    label: {
                        formatter: function(label, series) {
                            return "<div style='font-size:small; text-align: center; padding: 2px; color: " + series.color + "; font-weight: bold;'>" + label + "</div>";
                        }
                    }
                },
            },
            legend: {
                show: options.legend,
                labelFormatter: function(label, series) {
                    return label + ' (' + series.data[0][1].toFixed(options.significance) + ')';
                },
                container: (typeof options.legendBox == 'string') ? '.wrap_' + options.legendBox : null
            }
        }));
    });

});

