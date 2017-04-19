if ( typeof jQuery !== 'undefined' ) {
    (function($) {
        $(function() {
            /**
             * Formats a number with grouped thosands.
             * Taken from http://stackoverflow.com/a/14428340
             */
            var aucp_number_format = function( number, decimals, dec_point, thousands_sep ) {
                var re = '\\d(?=(\\d{' + (3 || 3) + '})+' + (decimals > 0 ? '\\D' : '$') + ')',
                    num = number.toFixed(Math.max(0, ~~decimals));

                return (decimals ? num.replace('.', dec_point) : num).replace(new RegExp(re, 'g'), '$&' + (thousands_sep || ',')); 
            };

            /**
             * Converts everything to a given target currency.
             */
            var aucp_convert_amounts = function( target ) {
                var sources = $('[data-unit-converter-currency-code]');
                var codes = sources.map(function() {
                    return $(this).attr('data-unit-converter-currency-code');
                }).get();

                // Convert.
                $.getJSON(aucp_js.ajaxurl, {
                    action: 'aucp_get_rates',
                    codes: $.unique(codes.concat(['USD', target]))
                }).done(function(data) {
                    sources.each(function() {
                        var $currencyAmount = $(this),
                            amount = $currencyAmount.attr('data-unit-converter-currency-amount'),
                            code = $currencyAmount.attr('data-unit-converter-currency-code'),
                            symbol = $currencyAmount.attr('data-unit-converter-currency-symbol'),
                            fromRate = data.rates[code].rate,
                            toRate = data.rates[target].rate,
                            newAmount = toRate * (parseFloat(amount) / fromRate),
                            // http://www.jacklmoore.com/notes/rounding-in-javascript/
                            roundedAmount = Number(Math.round(newAmount+'e2')+'e-2');

                        if ( code == target ) {
                            $currencyAmount.removeClass( 'aucp-converted' );
                            return;
                        }


                        var template = data.rates[target].format_template;
                        var formattedNumber = aucp_number_format( roundedAmount, data.rates[target].decimal_places, data.rates[target].decimal_point, data.rates[target].thousands_separator );

                        $currencyAmount.find( '.aucp-converted-text' ).html( template.replace( '<amount>', formattedNumber ) ).attr( 'title', data.rates[target].name );
                        $currencyAmount.addClass( 'aucp-converted' );
                    })
                });
            };


            var $widget = $( $('#aucp-currency-switcher-template').html() );

            $( 'body' ).click(function(e) {
                if ( ! $widget.dialog( 'isOpen' ) ) {
                    return;
                }

                var $target = $( e.target );

                if ( $target.closest( '.aucp-currency-amount, .ui-dialog' ).length > 0 ) {
                    return;
                }

                $widget.dialog( 'close' );
            });

            $widget.appendTo( $('body') ).dialog({
                autoOpen: false,
                dialogClass: 'aucp-currency-switcher-container',
                minHeight: 80,
            });

            var ENTER = 13,
                SPACE = 32,
                UP = 38,
                DOWN = 40,
                RIGHT = 39,
                LEFT = 37;

            $widget.on('keyup.aucp', 'input', function(e) {
                switch ( e.keyCode ) {
                    case ENTER:
                    case SPACE:
                        break;
                    case UP:
                        break;
                    case DOWN:
                        break;
                    default:
                        var search = $(this).val().toLowerCase();
                        var $allCurrencies = $widget.find('[data-content]').show();

                        if ( search.length ) {
                            $allCurrencies.not('[data-content*="' + search + '"]').hide();
                        }
                }
            });

            $widget.on('click', 'ul li', function(e) {
                var target = $(this).attr('data-code');

                // Save last currency selection.
                $.post( aucp_js.ajaxurl, { action: 'aucp_remember_currency', code: target } );

                // Convert.
                aucp_convert_amounts( target );

                $widget.dialog('close');
            });

            $search = $widget.find('input');

            $('.aucp-currency-amount').each(function() {
                $(this).click(function() {
                    $search.val('').trigger('keyup.aucp').focus();

                    $widget.dialog('option', 'position', { 
                        my: 'center top+10', 
                        at: 'bottom', 
                        of: $(this), 
                        collision: 'flipfit flipfit'
                    });
                    $widget.dialog('open');
                });
            });

            // If there's a default target currency, perform conversion.
            if ( aucp_js.default_target_currency && aucp_js.is_external_api_set) {
                aucp_convert_amounts( aucp_js.default_target_currency );
            }
        });
    })(jQuery);
}
