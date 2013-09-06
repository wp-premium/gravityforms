
/**
* Confirmation Editor
*
* Primarily hosts the confirmationEditor() object.
*
*/

function confirmationEditor(form) {
    this._form = form;
    this._hasUnsavedChanges = false;
    this._loaded = false;
    this._editing = false;

    this._editor = jQuery('#confirmation-editor');
    this._editorRow = this._editor.parents('tr');
    this._saveButton = this._editor.find('a#save-button');
    this._cancelLink = this._editor.find('a#cancel-link');
    this._updateMessage = this._editor.find('#update-message');

    this._labels = {
        save: gf_vars.confirmationSave,
        saving: gf_vars.confirmationSaving,
        areYouSure: gf_vars.confirmationAreYouSure,
        issueSaving: gf_vars.confirmationIssueSaving,
        confirmDelete: gf_vars.confirmationConfirmDelete,
        issueDeleting: gf_vars.confirmationIssueDeleting,
        confirmDiscard: gf_vars.confirmationConfirmDiscard,
        defaultName: gf_vars.confirmationDefaultName,
        defaultMessage: gf_vars.confirmationDefaultMessage
        };

    this._tempConf = [];

    this.confirmations = form.confirmations;

    this.init = function() {

        var editorObj = this;

        // bind change event to all form elements to detect when changes are made
        jQuery(document).on('change', this._editor.find('input, textarea, select'), function(){
            editorObj._hasUnsavedChanges = true;
        });

    }

    this.toggleConfirmation = function(isInit) {

        var isRedirect = jQuery("#form_confirmation_redirect").is(":checked");
        var isPage = jQuery("#form_confirmation_show_page").is(":checked");

        if(isRedirect){
            show_element = "#form_confirmation_redirect_container";
            hide_element = "#form_confirmation_message_container, #form_confirmation_page_container";
            jQuery("#form_confirmation_message").val("");
            jQuery("#form_disable_autoformatting").prop("checked", false);
            jQuery("#form_confirmation_page").val("");
        }
        else if(isPage){
            show_element = "#form_confirmation_page_container";
            hide_element = "#form_confirmation_message_container, #form_confirmation_redirect_container";
            jQuery("#form_confirmation_message").val("");
            jQuery("#form_disable_autoformatting").prop("checked", false);
            jQuery("#form_confirmation_page").val("");
            jQuery("#form_confirmation_url").val("");
            jQuery("#form_redirect_querystring").val("");
            jQuery("#form_redirect_use_querystring").prop("checked", false);
        }
        else{
            show_element = "#form_confirmation_message_container";
            hide_element = "#form_confirmation_page_container, #form_confirmation_redirect_container";
            jQuery("#form_confirmation_page").val("");
            jQuery("#form_confirmation_url").val("");
            jQuery("#form_redirect_querystring").val("");
            jQuery("#form_redirect_use_querystring").prop("checked", false);
        }

        var speed = isInit ? "" : "slow";

        jQuery(hide_element).hide(speed);
        jQuery(show_element).show(speed);

    }

    this.toggleQueryString = function(isInit){
        var speed = isInit ? "" : "slow";
        if(jQuery('#form_redirect_use_querystring').is(":checked")){
            jQuery('#form_redirect_querystring_container').show(speed);
        }
        else{
            jQuery('#form_redirect_querystring_container').hide(speed);
            jQuery("#form_redirect_querystring").val("");
            jQuery("#form_redirect_use_querystring").val("");
        }
    }

    this.cancelEdit = function($cancel_link) {

        var editorObj = this;

        if(editorObj._hasUnsavedChanges && !confirm(editorObj._labels.areYouSure))
            return;

        //redirect to confirmation list page
        location.href = $cancel_link;

    }

    this.save = function() {
        var editorObj = this;
        var spinner = gfAjaxSpinner(jQuery('#save-button'), gf_vars.baseUrl + '/images/spinner.gif');

        var confObj = this.getConfirmationFromUI();
        var isNew = confObj.id == 'new';      
        
        //perform some validation to make sure required data is provided
        switch(confObj.type) {
			case "page":
				//make sure page chosen
				if (confObj.pageId.length < 1){
					alert(gf_vars.confirmationInvalidPageSelection);
					spinner.destroy();
					return false;
				}
				break;
			case "redirect": {
				//make sure url is entered
				if (confObj.url.length < 1){
					alert(gf_vars.confirmationInvalidRedirect);
					spinner.destroy();
					return false;
				}
				break;
			}
        }

        if(this.isDefaultConfirmation(confObj.id)){
            confObj.isDefault = 1;
		}
		else{
			//make sure a confirmation name has been provided
			if (confObj.name.length < 1){
				alert(gf_vars.confirmationInvalidName);
				spinner.destroy();
				return false;
			}
		}

        this._saveButton.text(this._labels.saving);
        this._cancelLink.hide();

        jQuery.post(ajaxurl, {
            form_id: form.id,
            confirmation: confObj,
            action: 'gf_save_confirmation',
            gf_save_confirmation: gf_save_confirmation
        }, function(response){

            spinner.destroy();

            if(!response) {
                editorObj._saveButton.text(editorObj._labels.save);
                editorObj._cancelLink.show();
                alert(editorObj._labels.issueSaving);
                return;
            }

            var responseData = jQuery.parseJSON(response);
            var confObj = responseData.confObj;
            var data = responseData.data;

            if(isNew) {
                var confirmationRow = jQuery('tr#confirmation-new');
                confirmationRow.attr('id', 'confirmation-' + confObj.id);
            }

            editorObj.confirmations[confObj.id] = confObj;
            editorObj._hasUnsavedChanges = false;
            editorObj._saveButton.text(editorObj._labels.save);
            editorObj._cancelLink.show();
            editorObj._updateMessage.show();
            setTimeout("jQuery('#update-message').hide('slow');", 4000);

        });

    }

    this.confirmAndDelete = function(confId, linkElem) {
        if(!confirm(this._labels.confirmDelete) || this.isDefaultConfirmation(confId)){
            return;
		}

        this.delete(confId, linkElem);

    }

    this.delete = function(confId, linkElem) {
        var editorObj = this;
        var spinner = gfAjaxSpinner(jQuery(linkElem).parents('td').find('strong'), gf_vars.baseUrl + '/images/spinner.gif');
        
        if(confId && confId != 'new') {

            jQuery.post(ajaxurl, {
                form_id: form.id,
                confirmation_id: confId,
                action: 'gf_delete_confirmation',
                gf_delete_confirmation: gf_delete_confirmation
            }, function(response){

                spinner.destroy();

                if(!response) {
                    alert(editorObj._labels.issueDeleting);
                    return;
                }

                delete editorObj.confirmations[confId];
                editorObj.deleteRow(confId);

            });

        } else {

            delete editorObj.confirmations[confId];
            editorObj.deleteRow(confId);

        }

    }

    this.deleteRow = function(confId) {
        var confirmationRow = jQuery('tr#confirmation-' + confId);
        confirmationRow.remove();
    }

    this.getConfirmationFromUI = function() {

        var confirmation = new this.confirmationObj(this);

        confirmation.id = jQuery('input#confirmation_id').val();
        confirmation.name = jQuery('input#form_confirmation_name').val();
        confirmation.type = jQuery('input[name="form_confirmation"]').filter(':checked').val();
        confirmation.message = jQuery('textarea#form_confirmation_message').val();
        if (jQuery('input#form_disable_autoformatting').prop('checked')){
			confirmation.disableAutoformat = jQuery('input#form_disable_autoformatting').val();	
        }
        else{
			confirmation.disableAutoformat = "";
        }
        confirmation.pageId = jQuery('select#form_confirmation_page').val();
        confirmation.url = jQuery('input#form_confirmation_url').val();
        if (jQuery('input#form_redirect_use_querystring').prop('checked')){
        	confirmation.queryString = jQuery('#form_redirect_querystring').val();
		}
		else{
			confirmation.queryString = "";
		}
		//populate the conditional logic data when enabled
        if (jQuery('input#confirmation_conditional_logic').prop('checked')){
        	confirmation.conditionalLogic = this._tempConf.conditionalLogic;
		}

        return confirmation;
    }

    this.isDefaultConfirmation = function(confId) {

        if(confId == 'new')
            return false;

        return this.confirmations[confId] && this.confirmations[confId].isDefault;
    }

    this.confirmationObj = function(editorObj) {
        this.id = 'new';
        this.name = editorObj._labels.defaultName;
        this.type = 'message';
        this.message = editorObj._labels.defaultMessage,
        this.isDefault = 0
    }

    this.init();

}