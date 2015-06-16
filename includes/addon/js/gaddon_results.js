var gresultsAjaxRequest;

var gresults = {

    drawCharts: function () {
        var containers = jQuery('.gresults-chart-wrapper');
        containers.each(function (index, elem) {
            var id = jQuery(elem).attr('id');
            var options = jQuery(elem).data('options');
            var datatable = jQuery(elem).data('datatable');
            var chartType = jQuery(elem).data('charttype');
            var data_array = datatable;
            var data = google.visualization.arrayToDataTable(data_array);
            var cont = document.getElementById(id);
            var chart;
            if (chartType == "bar") {
                chart = new google.visualization.BarChart(cont);
            } else if (chartType == "pie") {
                chart = new google.visualization.PieChart(cont);
            } else if (chartType == "column") {
                chart = new google.visualization.ColumnChart(cont);
            }
            chart.draw(data, options);
        });
    },

    renderStateData: function (state) {
        var results = jQuery("#gresults-results");
        results.data('searchcriteria', state.searchCriteria);
        jQuery("#gresults-results-filter").html(state.filterUI);
        results.css('opacity', 0);
        results.html(state.html);
        gresults.drawCharts();
        results.fadeTo("slow", 1);

        var filterContainer = jQuery("#gresults-results-field-filters-container");
        filterContainer.resizable();
        filterContainer.resizable('destroy');
        filterContainer.resizable({
            handles: 's'
        });
    },

    getResults: function () {
        gresults.recordFormState();
        var gresultsData = jQuery('#gresults-results-filter-form').serialize();
        gresults.sendRequest(gresultsData)
    },

    sendRequest: function (gresultsData, serverStateObject, checkSum) {
        var results = jQuery("#gresults-results");
        var filterButtons = jQuery("#gresults-results-filter-buttons input");
        var loading = jQuery(".gresults-filter-loading");
        var viewSlug = jQuery("#gresults-view-slug").val();
        var data_str = "action=gresults_get_results_" + viewSlug + "&" + gresultsData;
        if (serverStateObject)
            data_str += "&state=" + serverStateObject + "&checkSum=" + checkSum;

        gresultsAjaxRequest = jQuery.ajax({
            url       : ajaxurl,
            type      : 'POST',
            dataType  : 'json',
            data      : data_str,
            beforeSend: function (xhr, opts) {
                results.fadeTo("slow", 0.33);
                results.html('');
                loading.show();
                filterButtons.attr('disabled', 'disabled');
            }
        })
        .done(function (response) {
            if (!response || response === -1) {
                loading.hide();
                results.html(gresultsStrings.ajaxError);
            } else {
                if (response.status === "complete") {
                    filterButtons.removeAttr('disabled');
                    loading.hide();
                    results.html(response.html);
                    jQuery("#gresults-results").data('searchcriteria', response.searchCriteria); //used in 'more' links

                    var filterUI = jQuery("#gresults-results-filter").html();

                    gresults.drawCharts();
                    results.fadeTo("slow", 1);
                    if (window.history.replaceState) {
                        if (!history.state) {
                            history.replaceState({"html": response.html, "filterUI": filterUI, "searchCriteria": response.searchCriteria}, "", "?" + gresultsData);
                        } else {
                            history.pushState({"html": response.html, "filterUI": filterUI, "searchCriteria": response.searchCriteria}, "", "?" + gresultsData);
                        }
                    }
                    gresults.drawCharts();
                    if (window["gform_initialize_tooltips"])
                        gform_initialize_tooltips();
                } else if (response.status === "incomplete") {
                    serverStateObject = response.stateObject;
                    gresults.sendRequest(gresultsData, serverStateObject, response.checkSum);
                    results.html(response.html);
                } else {
                    loading.hide();
                    results.html(gresultsStrings.ajaxError);
                }
            }
        })
        .fail(function (error) {
            filterButtons.removeAttr('disabled');
            results.fadeTo("fast", 1);
            var msg = error.statusText;
            loading.hide();
            if (msg == "abort") {
                msg = "Request cancelled";
            } else {
                msg = gresultsStrings.ajaxError;
            }
            results.html(msg);
        })
    },

    getMoreResults: function (formId, fieldId) {
        var container = jQuery('#gresults-results-field-content-' + fieldId);
        var results = jQuery("#gresults-results");
        var offset = jQuery(container).data('offset');
        var viewSlug = jQuery("#gresults-view-slug").val();
        var searchCriteria = results.data('searchcriteria');
        jQuery.ajax({
            url     : ajaxurl,
            type    : 'POST',
            dataType: 'json',
            data    : {
                action: 'gresults_get_more_results_' + viewSlug,
                view: viewSlug,
                form_id: formId,
                field_id: fieldId,
                offset: offset,
                search_criteria: searchCriteria
            },
            success : function (response) {
                if (response === -1) {
                    //permission denied
                }
                else {
                    if (response.html)
                        jQuery(container).append(response.html);
                    if (!response.more_remaining)
                        jQuery('#gresults-results-field-more-link-' + fieldId).hide();

                    jQuery(container).data('offset', response.offset);
                }
            }
        });

        return false;

    },

    clearFilterForm: function () {
        jQuery("#gresults-results-field-filters-container").gfFilterUI(gresultsFilterSettings, [], true);
        jQuery('#gresults-results-filter-form').find('input, select').each(function () {
            switch (this.type) {
                case 'text':
                case 'select-one':
                    jQuery(this).val('').change();
                    break;
                case 'checkbox':
                case 'radio':
                    this.checked = false;
            }
        });
    },

    recordFormState: function () {
        jQuery("#gresults-results-filter-form input[type='radio']").each(function () {
            if (this.checked) {
                jQuery(this).prop("defaultChecked", true);
            } else {
                jQuery(this).prop("defaultChecked", false);
            }
        });
        jQuery("#gresults-results-filter-form input[type='checkbox']").each(function () {
            if (this.checked) {
                jQuery(this).prop("defaultChecked", true);
            } else {
                jQuery(this).prop("defaultChecked", false);
            }
        });
        jQuery("#gresults-results-filter-form input[type='text']").each(function () {
            jQuery(this).prop("defaultValue", jQuery(this).val());
        });
        jQuery("#gresults-results-filter-form select option").each(function () {
            jQuery(this).prop("defaultSelected", jQuery(this).prop('selected'));
        });
    },

    setCustomFilter: function(key, value){
        elementId = "gresults-custom-" + key;
        if(jQuery('#' + elementId).length == 0)
            jQuery('#gresults-results-filter-form').append("<input type='hidden' id='" + elementId + "' name='" + key + "' value='" + value + "'>");
        else
            jQuery('#' + elementId).val(value);
    }

};

google.load('visualization', '1', {packages: ['corechart']});
google.setOnLoadCallback(gresults.drawCharts);


jQuery(document).ready(function () {

    if (jQuery("#gresults-results").length > 0) {

        jQuery("#gresults-results-field-filters-container").gfFilterUI(gresultsFilterSettings, gresultsInitVars, true);
        var $window = jQuery(window);

         $window.resize(function (e) {
         if (e.target === window) {
             gresults.drawCharts();
             }
         });

        window.onpopstate = function (e) {
            if (e.state)
                gresults.renderStateData(e.state)
        };


        jQuery("#gresults-results-filter-date-start, #gresults-results-filter-date-end").datepicker({dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true});

        jQuery("#gresults-results-filter-form").submit(function (e) {
            gresults.getResults();
            return false;
        });

        if (history.state) {
            gresults.renderStateData(history.state)
        } else {
            gresults.getResults();
        }
        if (window["gform_initialize_tooltips"])
            gform_initialize_tooltips();

    }
});