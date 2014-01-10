(function (gfFieldFilterUI, $) {

    $.fn.gfFilterUI = function(filterSettings, initVars, allowMultiple, minResizeHeight) {
        init(this, filterSettings, initVars, allowMultiple, minResizeHeight );
        return this;
    };

    // private
    var $container, operatorStrings, settings, filters, mode, imagesURL, isResizable, allowMultiple, height;

    function init (c, s, initVars, m, h){
        $container = $(c);
        $container
            .css('position' , 'relative')
            .html('<div id="gform-field-filters"></div>');
        height = h;
        isResizable = typeof height != 'undefined' && height > 0;
        operatorStrings = {"is":"is","isnot":"isNot", ">":"greaterThan", "<":"lessThan", "contains":"contains", "starts_with":"startsWith", "ends_with":"endsWith"};
        imagesURL = gf_vars.baseUrl + "/images";
        settings = s;
        filters = initVars && initVars.filters ? initVars.filters : [];
        mode = initVars && initVars.mode ? initVars.mode : "all";
        allowMultiple = typeof m == 'undefined' || m ? true : false ;

        setUpFilters(filters);

    }

    function setUpFilters(filters) {
        var i;
        if (typeof filters == 'undefined' || filters.length == 0){
            displayNoFiltersMessage();
            return;
        }

        if(mode != "off")
            $("#gform-field-filters").append(getFilterMode(mode));

        for (i = 0; i < filters.length; i++) {
            $("#gform-field-filters").append(getNewFilterRow());
        }
        $(".gform-filter-field").each(function (i) {
            var fieldId = filters[i].field;
            jQuery(this).val(fieldId);
            jQuery(this).change();
        });
        $(".gform-filter-operator").each(function (i) {
            var operator = filters[i].operator;
            jQuery(this).val(operator);
            jQuery(this).change();
        });
        $(".gform-filter-value").each(function (i) {
            var value = filters[i].value;
            jQuery(this).val(value);
            jQuery(this).change();
        });
        maybeMakeResizable()
    }

    function getNewFilterRow() {
        var str;
        str = "<div class='gform-field-filter'>";
        str += getFilterFields() + getFilterOperators() + getFilterValues() + getAddRemoveButtons();
        str += "</div>";
        return str;
    }

    function getFilterFields() {
        var i, j, key, val, label, question, options, disabled = "", numRows,
            select = [];
        select.push("<select class='gform-filter-field' name='f[]' onchange='gfFilterUI.changeField(this)'>");
        for (i = 0; i < settings.length; i++) {
            key = settings[i].key;
            if (settings[i].group) {
                question = settings[i].text;
                numRows = settings[i].filters.length;
                options = [];
                for (j = 0; j < numRows; j++) {
                    label = settings[i].filters[j].text;
                    val = settings[i].filters[j].key;
                    disabled = isFieldSelected(val) ? 'disabled="disabled"' : "";
                    options.push('<option {0} value="{1}">{2}</option>'.format(disabled, val, label));
                }
                select.push('<optgroup label="{0}">{1}</optgroup>'.format(question, options.join('')));
            } else {
                disabled = settings[i].preventMultiple && isFieldSelected(key) ? "disabled='disabled'" : "";
                label = settings[i].text;
                select.push('<option {0} value="{1}">{2}</option>'.format(disabled, key, label));
            }

        }
        select.push("</select>");
        select.push("<input type='hidden' class='gform-filter-type' name='t[]' value='' >");
        return select.join('');
    }

    gfFilterUI.changeField = function (fieldSelect) {
        var filter = getFilter(fieldSelect.value)
        if (filter) {
            $(fieldSelect).siblings(".gform-filter-value").replaceWith(getFilterValues(filter));
            $(fieldSelect).siblings(".gform-filter-type").val(filter.type);
            $(fieldSelect).siblings(".gform-filter-operator").replaceWith(getFilterOperators(filter));
        }
        setDisabledFields();
    }

    function isFieldSelected (fieldId) {
        fieldId = fieldId.toString();
        var selectedFields = [];
        $('.gform-filter-field :selected').each(function (i, selected) {
            selectedFields[i] = $(selected).val();
        });
        return $.inArray(fieldId, selectedFields) > -1 ? true : false;
    }

    function getFilterOperators (filter) {
        var i, operator,
            str = "<select name='o[]' class='gform-filter-operator'>";
        if (filter) {
            for (i = 0; i < filter.operators.length; i++) {
                operator = filter.operators[i];
                str += "<option value='{0}'>{1}</option>".format(operator, gf_vars[operatorStrings[operator]] );
            }
        }
        str += "</select>";
        return str;
    }

    function getFilterValues (filter) {
        var i, val, text, str, options = "";
        str = "<input type='text' value='' name='v[]' class='gform-filter-value' />";

        if (filter) {
            if (filter.values) {
                for (i = 0; i < filter.values.length; i++) {
                    val = filter.values[i].value;
                    text = filter.values[i].text;
                    options += "<option value='{0}'>{1}</option>".format(val, text);
                }
                str = "<select name='v[]' class='gform-filter-value'>{0}</select>".format(options);
            }
        }

        return str;
    }


    function getFilter (key) {
        if (!key)
            return;
        for (var i = 0; i < settings.length; i++) {
            if (key == settings[i].key)
                return settings[i];
            if (settings[i].group) {
                for (var j = 0; j < settings[i].filters.length; j++) {
                    if (key == settings[i].filters[j].key)
                        return settings[i].filters[j];
                }
            }

        }
    }

    function getAddRemoveButtons () {
        var str = "";
        if(!allowMultiple)
            return str;

        str += "<img class='gform-add' onclick='gfFilterUI.addNewFieldFilter(this)' src='{0}/add.png' alt='{1}' title='{2}'>".format(imagesURL, gf_vars.addFieldFilter, gf_vars.addFieldFilter);
        str += "<img class='gform-remove' onclick='gfFilterUI.removeFieldFilter(this)' src='" + imagesURL + "/remove.png' alt='" + gf_vars.removeFieldFilter + "' title='" + gf_vars.removeFieldFilter + "'>";
        return str;
    }

    function maybeMakeResizable () {
        if(!isResizable)
            return;

        var $filterBox = $("#gform-field-filters");

        var $filters = $(".gform-field-filter");

        if ($filters.length <= 1) {
            if ($($container).hasClass('ui-resizable'))
                $container.resizable('destroy');
            return;
        }
        var makeResizable = ($filterBox.get(0).scrollHeight > $container.height()) || $container.height() >= height;

        if (makeResizable) {
            $container
                .css({'min-height': height + 'px' , 'border-bottom': '5px double #DDD'})
                .resizable({
                    handles  : 's',
                    minHeight: height
                });
            $filterBox.css("min-height", height);
        } else {
            $container.css({'min-height': '', 'border-bottom': ''});
        }
    }

    function displayNoFiltersMessage () {
        var str = "";
        str += "<div id='gform-no-filters' onclick='gfFilterUI.addNewFieldFilter(this);jQuery(this).remove();' >" + gf_vars.addFieldFilter;
        str += "<img class='gform-add' src='{0}/add.png' alt='{1}' title='{2}'></div>".format(imagesURL, gf_vars.addFieldFilter, gf_vars.addFieldFilter);
        $("#gform-field-filters").html(str);
        if(isResizable){
            $container.css({'min-height': '', 'border-bottom': ''});
            $container.height(80);
            $("#gform-field-filters").css("min-height", '');
        }

    }

    function setDisabledFields () {
        $("select.gform-filter-field option").removeAttr("disabled");
        $("select.gform-filter-field").each(function (i) {
            var filter = getFilter(this.value);
            if (typeof(filter) != 'undefined' && filter.preventMultiple && isFieldSelected(this.value)) {
                $("select.gform-filter-field option[value='" + this.value + "']:not(:selected)").attr('disabled', 'disabled');
            }
        });

    }

    function getFilterMode(mode){
        var html;
        html = '<select name="mode"><option value="all" {0}>{1}</option><option value="any" {2}>{3}</option></select>'.format(selected("all", mode), gf_vars.all, selected("any", mode), gf_vars.any);
        html = gf_vars.filterAndAny.format(html);
        return html
    }

    function selected(selected, current){
        return selected == current ? 'selected="selected"' : "";
    }

    function addFilterMode ($filterRow) {

        $filterRow.after(getFilterMode());
    }

    gfFilterUI.addNewFieldFilter = function (el) {
        var $el, $filterRow;
        $el = $(el);
        if($el.is("img"))
            $filterRow = $el.parent();
        else
            $filterRow = $el;

        $filterRow.after(getNewFilterRow());
        $filterRow.next("div").find(".gform-filter-field").change();
        if ($(".gform-field-filter").length == 1)
            addFilterMode($filterRow);

        maybeMakeResizable();
    }

    gfFilterUI.removeFieldFilter = function (img) {
        $(img).parent().remove();
        if ($(".gform-field-filter").length == 0)
            displayNoFiltersMessage();
        setDisabledFields();
        maybeMakeResizable();
    }

    String.prototype.format = function () {
        var args = arguments;
        return this.replace(/{(\d+)}/g, function (match, number) {
            return typeof args[number] != 'undefined'
                ? args[number]
                : match
                ;
        });
    };

}(window.gfFilterUI = window.gfFilterUI || {}, jQuery));