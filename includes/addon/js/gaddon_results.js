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
        jQuery("#gresults-results").data('searchcriteria', state.searchCriteria)
        jQuery("#gresults-results-filter").html(state.filterUI);
        jQuery("#gresults-results").css('opacity', 0);
        jQuery("#gresults-results").html(state.html);
        gresults.drawCharts();
        jQuery("#gresults-results").fadeTo("slow", 1);

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
                if (response === -1) {
                    //permission denied
                }
                else {
                    if (response.status === "complete") {
                        filterButtons.removeAttr('disabled');
                        loading.hide();
                        results.html(response.html);
                        jQuery("#gresults-results").data('searchcriteria', response.searchCriteria) //used in 'more' links

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
        var searchCriteria = jQuery("#gresults-results").data('searchcriteria');
        jQuery.ajax({
            url     : ajaxurl,
            type    : 'POST',
            dataType: 'json',
            data    : {action: 'gresults_get_more_results_' + viewSlug, view: viewSlug, form_id: formId, field_id: fieldId, offset: offset, search_criteria: searchCriteria},
            success : function (response) {
                if (response === -1) {
                    //permission denied
                }
                else {

                    if (response.html)
                        jQuery(container).append(response.html);
                    if (response.remaining <= 0)
                        jQuery('#gresults-results-field-more-link-' + fieldId).hide();

                    jQuery(container).data('offset', offset + 10);
                }
            }
        });

        return false;

    },

    setUpFilter: function () {
        var i, j, k, l;
        if (gresultsVars.filterFields.length == 0)
            gresults.displayNoFieldsMessage();
        for (var i = 0; i < gresultsVars.filterFields.length; i++) {
            jQuery("#gresults-results-field-filters").append(gresults.getNewFilterRow());
        }
        jQuery(".gresults-filter-field").each(function (j) {
            var fieldId = gresultsVars.filterFields[j];
            jQuery(this).val(fieldId);
            jQuery(this).change();
        });
        jQuery(".gresults-filter-operator").each(function (k) {
            var operator = gresultsVars.filterOperators[k];
            jQuery(this).val(operator);
            jQuery(this).change();
        });
        jQuery(".gresults-filter-value").each(function (l) {
            var value = gresultsVars.filterValues[l];
            jQuery(this).val(value);
            jQuery(this).change();
        });
        gresults.maybeMakeResizable()
    },

    clearFilterForm: function () {
        gresults.displayNoFieldsMessage();
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

    getNewFilterRow: function () {
        var str;
        str = "<div class='gresults-results-field-filter'>";
        str += gresults.getFilterFields() + gresults.getFilterOperators() + gresults.getFilterValues() + gresults.getAddRemoveButtons();
        str += "</div>";
        return str;
    },

    getFilterFields: function () {
        var i, j, key, val, label, question, options = "", disabled = "", multipleRows, numRows,
            str = "<select class='gresults-filter-field' name='f[]' onchange='gresults.changeField(this)'>";
        for (i = 0; i < gresultsFilters.length; i++) {
            key = gresultsFilters[i].key;
            if (gresultsFilters[i].type == 'group') {
                question = gresultsFilters[i].text;
                numRows = gresultsFilters[i].filters.length;
                for (j = 0; j < numRows; j++) {
                    label = gresultsFilters[i].filters[j].text;
                    val = gresultsFilters[i].filters[j].key;
                    disabled = gresults.isFieldSelected(val) ? 'disabled="disabled"' : "";
                    options += '<option ' + disabled + ' value="' + val + '">' + label + '</option>';
                }
                str += '<optgroup label="' + question + '">' + options + '</optgroup>';
            } else {
                disabled = gresultsFilters[i].preventMultiple && gresults.isFieldSelected(key) ? "disabled='disabled'" : "";
                label = gresultsFilters[i].text;
                str += '<option ' + disabled + ' value="' + key + '">' + label + '</option>';
            }

        }
        str += "</select>";
        str += "<input type='hidden' class='gresults-filter-type' name='t[]' value='' >";
        return str;
    },

    changeField: function (fieldSelect) {
        var filter = gresults.getFilter(fieldSelect.value)
        if (filter) {
            jQuery(fieldSelect).siblings(".gresults-filter-value").replaceWith(gresults.getFilterValues(filter));
            jQuery(fieldSelect).siblings(".gresults-filter-type").val(filter.type);
            jQuery(fieldSelect).siblings(".gresults-filter-operator").replaceWith(gresults.getFilterOperators(filter));
        }
        gresults.setDisabledFields();
    },

    isFieldSelected: function (fieldId) {
        fieldId = fieldId.toString();
        var selectedFields = [];
        jQuery('.gresults-filter-field :selected').each(function (i, selected) {
            selectedFields[i] = jQuery(selected).val();
        });
        return jQuery.inArray(fieldId, selectedFields) > -1 ? true : false;
    },

    getFilterOperators: function (filter) {
        var str = "<select name='o[]' class='gresults-filter-operator'>", operator;
        if (filter) {
            for (i = 0; i < filter.operators.length; i++) {
                operator = filter.operators[i];
                str += "<option value='" + operator + "'>" + gresultsOperatorStrings[operator] + "</option>";
            }
        }
        str += "</select>";
        return str;
    },

    getFilterValues: function (filter) {
        var i, val, text, str, options = "";
        str = "<input type='text' value='' name='v[]' class='gresults-filter-value' />";

        if (filter) {
            if (filter.values) {
                for (i = 0; i < filter.values.length; i++) {
                    val = filter.values[i].value;
                    text = filter.values[i].text;
                    options += "<option value='" + val + "'>" + text + "</option>";
                }
                str = "<select name='v[]' class='gresults-filter-value'>" + options + "</select>";
            }
        }

        return str;
    },

    cleanFieldId: function (fieldId) {
        if (fieldId.indexOf("-") !== -1) {
            var fieldIdArray = fieldId.split('-');
            fieldId = fieldIdArray[0];
        }
        return fieldId
    },

    getFilter: function (key) {
        if (!key)
            return;
        for (var i = 0; i < gresultsFilters.length; i++) {
            if (key == gresultsFilters[i].key)
                return gresultsFilters[i];
            if (gresultsFilters[i].type == "group") {
                for (var j = 0; j < gresultsFilters[i].filters.length; j++) {
                    if (key == gresultsFilters[i].filters[j].key)
                        return gresultsFilters[i].filters[j];
                }
            }

        }
    },

    getFieldIndex: function (fieldId) {
        for (var i = 0; i < gresultsFields.length; i++) {
            if (gresultsFields[i].id == fieldId)
                return i;
        }
    },

    getAddRemoveButtons: function () {
        var str = "";
        str += "<img class='gresults-add' onclick='gresults.addNewFieldFilter(this)' src='" + gresultsVars.imagesUrl + "/add.png' alt='" + gresultsStrings.addFieldFilter + "' title='" + gresultsStrings.addFieldFilter + "'>";
        str += "<img class='gresults-remove' onclick='gresults.removeFieldFilter(this)' src='" + gresultsVars.imagesUrl + "/remove.png' alt='" + gresultsStrings.removeFieldFilter + "' title='" + gresultsStrings.removeFieldFilter + "'>";
        return str;
    },

    addNewFieldFilter: function (img) {
        jQuery(img).parent().after(gresults.getNewFilterRow());
        jQuery(img).parent().next("div").find(".gresults-filter-field").change();
        gresults.maybeMakeResizable();
    },

    maybeMakeResizable: function () {
        var filterBox = jQuery("#gresults-results-field-filters");
        var filters = jQuery(".gresults-results-field-filter");
        var filtersContainer = jQuery("#gresults-results-field-filters-container");
        if (filters.length <= 1) {
            if (jQuery(filtersContainer).hasClass('ui-resizable'))
                filtersContainer.resizable('destroy');
            return;
        }
        var isResizable = (filterBox.get(0).scrollHeight > filtersContainer.height()) || filtersContainer.height() >= 120;
        filtersContainer.toggleClass("resizable", isResizable);
        if (isResizable) {
            filtersContainer.resizable({
                handles  : 's',
                minHeight: 120
            });
            filterBox.css("min-height", 120);
        }
    },

    removeFieldFilter: function (img) {
        jQuery(img).parent().remove();
        if (jQuery(".gresults-results-field-filter").length == 0)
            gresults.displayNoFieldsMessage();
        gresults.setDisabledFields();
        gresults.maybeMakeResizable();
    },

    displayNoFieldsMessage: function () {
        var str = "";
        str += "<div id='gresults-no-filters'>" + gresultsStrings.noFilters;
        str += "<img class='gresults-add' onclick='gresults.addNewFieldFilter(this);jQuery(this).parent().remove();' src='" + gresultsVars.imagesUrl + "/add.png' alt='" + gresultsStrings.addFieldFilter + "' title='" + gresultsStrings.addFieldFilter + "'></div>";
        jQuery("#gresults-results-field-filters").html(str);
        jQuery("#gresults-results-field-filters-container").removeClass('resizable');
        jQuery("#gresults-results-field-filters-container").height(55);
        jQuery("#gresults-results-field-filters").css("min-height", '');
    },

    setDisabledFields: function () {
        jQuery("select.gresults-filter-field option").removeAttr("disabled");
        jQuery("select.gresults-filter-field").each(function (i) {
            var filter = gresults.getFilter(this.value);
            if (typeof(filter) != 'undefined' && filter.preventMultiple && gresults.isFieldSelected(this.value)) {
                jQuery("select.gresults-filter-field option[value='" + this.value + "']:not(:selected)").attr('disabled', 'disabled');
            }
        });

    },

    getFieldType: function (fieldId) {
        for (var i = 0; i < gresultsFields.length; i++) {
            if (gresultsFields[i].id == fieldId)
                return gresultsFields[i].inputType;
        }
    }


};

google.load('visualization', '1', {packages: ['corechart']});
google.setOnLoadCallback(gresults.drawCharts);


jQuery(document).ready(function () {

    if (jQuery("#gresults-results").length > 0) {
        var $window = jQuery(window),
            filter = jQuery('#gresults-results-filter'),
            filterTop = filter.offset().top,
            gresultsIsFilterSticky,
            resultsDiv = jQuery('#gresults-results'),
            gresultsFilterLeftMargin = 20,
            gresultsFilterLeft,
            gresultsFilterRelativeLeft;

        function gresultsPostionFilterUI() {
            gresultsFilterLeft = resultsDiv.width() + resultsDiv.offset().left + gresultsFilterLeftMargin;
            filter.offset({left: gresultsFilterLeft});
            gresultsFilterRelativeLeft = resultsDiv.width() + gresultsFilterLeftMargin;
            jQuery("#gresults-results-filter").css('visibility', 'visible');
        }

        $window.scroll(function (e) {
            var newIsFilterSticky = $window.scrollTop() > filterTop - 30;
            if (gresultsIsFilterSticky != newIsFilterSticky) {
                gresultsIsFilterSticky = newIsFilterSticky
                if (gresultsIsFilterSticky) {
                    filter.css("left", gresultsFilterLeft);
                } else {
                    filter.css("left", gresultsFilterRelativeLeft);
                }
            }
            filter.toggleClass('sticky', gresultsIsFilterSticky);
        });

        window.onpopstate = function (e) {
            if (e.state)
                gresults.renderStateData(e.state)
        };

        $window.resize(function (e) {
            if (e.target === window) {
                gresultsPostionFilterUI();
                gresults.drawCharts();
            }

        });

        gresultsPostionFilterUI();
        gresults.setUpFilter();

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