jQuery(function() {
    jQuery.fn.dataTableExt.oSort['string-num-asc']  = function(x1,y1) {
        var x=x1;
        var y=y1;
        var pattern = /[0-9]+/g;
            var matches;
        if(x1.length !== 0) {
            matches = x1.match(pattern);
            x=matches[0];
        }
        if(y1.length !== 0) {
            matches = y1.match(pattern);
            y=matches[0];
        }
        x = parseInt( x, 10 );
        y = parseInt( y, 10 );
        return ((x < y) ? -1 : ((x > y) ?  1 : 0));

    };

    jQuery.fn.dataTableExt.oSort['string-num-desc'] = function(x1,y1) {

        var x=x1;
        var y=y1;
        var pattern = /[0-9]+/g;
            var matches;
        if(x1.length !== 0) {
            matches = x1.match(pattern);
            x=matches[0];
        }
        if(y1.length !== 0) {
            matches = y1.match(pattern);
            y=matches[0];
        }
        x = parseInt( x, 10 );
        y = parseInt( y, 10 );
        return ((x < y) ?  1 : ((x > y) ? -1 : 0));
    };

    jQuery.fn.dataTableExt.oSort['numeric_ignore_nan-asc']  = function(x,y) {
        if (isNaN(x) && isNaN(y)) return ((x < y) ? 1 : ((x > y) ?  -1 : 0));

        if (isNaN(x)) return 1;
        if (isNaN(y)) return -1;

        x = parseFloat( x );
        y = parseFloat( y );
        return ((x < y) ? -1 : ((x > y) ?  1 : 0));
    };

    jQuery.fn.dataTableExt.oSort['numeric_ignore_nan-desc'] = function(x,y) {
        if (isNaN(x) && isNaN(y)) return ((x < y) ? 1 : ((x > y) ?  -1 : 0));

        if (isNaN(x)) return -1;
        if (isNaN(y)) return 1;

        x = parseFloat( x );
        y = parseFloat( y );
        return ((x < y) ?  1 : ((x > y) ? -1 : 0));
    };

    jQuery('table.shoptable_' + table_name).dataTable({
        bPaginate: false,
        bLengthChange: false,
        aaSorting: sort_columns,
        aoColumnDefs: [
            { sType: 'numeric_ignore_nan', aTargets: numeric_columns },
            { sType: 'string-num', aTargets: strnum_columns },
            { bVisible: false, aTargets: jQuery('table.shoptable_' + table_name + ' thead th').size() > hide_cols ? [hide_cols] : [] }, // Hide the timestamps by default
        ]
    });

    var asInitVals = new Array();
    var oTable = jQuery('table.shoptable_' + table_name).dataTable();
    jQuery("thead input").click( function(e) {
        e.stopPropagation();
    });

    jQuery("thead input").keyup( function () {
            // Filter on the column (the index) of this element
            var myIndex = jQuery("thead input").index(this);
            if (jQuery(this).hasClass('numeric')) {
                oTable.fnDraw();
            } else {
                oTable.fnFilter( this.value, myIndex );
            }
    } );

    jQuery.fn.dataTableExt.afnFiltering.push(
        function( oSettings, aData, iDataIndex ) {
            var display = true;
            jQuery("thead input.numeric").each(function() {
                if(jQuery(this).val() == "") {
                    return;
                }
                var myIndex = jQuery("thead input").index(this);
                if (jQuery(this).val().indexOf(">") == 0) {
                    if( aData[myIndex]*1 > jQuery(this).val().substr(1)*1 ) {
                        return;
                    } else {
                        display = false;
                        return;
                    }
                }
                else if (jQuery(this).val().indexOf("<") == 0) {
                    if( aData[myIndex]*1 < jQuery(this).val().substr(1)*1 ) {
                        return;
                    } else {
                        display = false;
                        return;
                    }
                }
                else {
                    if( aData[myIndex]*1 == jQuery(this).val()*1 ) {
                        return;
                    } else {
                        display = false;
                        return;
                    }
                }
            });
            return display;
        }
    );


    /*
     * Support functions to provide a little bit of 'user friendlyness' to the textboxes in
     * the footer
     */

    jQuery("thead input").each( function (i) {
        asInitVals[i] = this.value;
    } );

    jQuery("thead input").focus( function () {
        if ( this.className == "search_init" )
        {
            jQuery(this).removeClass('search_init');
            this.value = "";
        }
    } );

    jQuery("thead input").blur( function (i) {
        if ( this.value == "" )
        {
            this.addClass('search_init');
            this.value = asInitVals[jQuery("thead input").index(this)];
        }
    } );
});

function fnShowHide( iCol )
{
    /* Get the DataTables object again - this is not a recreation, just a get of the object */
    var oTable = jQuery('table.shoptable_' + table_name).dataTable();

    var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
    oTable.fnSetColumnVis( iCol, bVis ? false : true );
}
