<script type="text/html" id="tmpl-gf-shortcode-default-edit-form">

	<form class="gf-edit-shortcode-form">

		<h2 class="gf-edit-shortcode-form-title"><?php _e('Insert A Form', 'gravityforms'); ?></h2>
		<br />
		<div class="gf-edit-shortcode-form-required-attrs">
		</div>
		<br />
		<div class="gf-edit-shortcode-form-standard-attrs">
		</div>
		<br />
		<div>
			<a href="javascript:void(0);" onclick="jQuery('#gf-edit-shortcode-form-advanced-attrs').toggle();" onkeypress="jQuery('#gf-edit-shortcode-form-advanced-attrs').toggle();" ><?php _e('Advanced Options', 'gravityforms'); ?></a>
		</div>
		<br />
		<div id="gf-edit-shortcode-form-advanced-attrs" class="gf-edit-shortcode-form-advanced-attrs" style="display:none;">
		</div>

		<input id="gform-update-shortcode" type="button" class="button-primary" value="<?php _e( 'Update Form', 'gravityforms' ); ?>" />
		<input id="gform-insert-shortcode" type="button" class="button-primary" value="<?php _e( 'Insert Form', 'gravityforms' ); ?>" />&nbsp;&nbsp;&nbsp;
		<a id="gform-cancel-shortcode" class="button" style="color:#bbb;" href="#"><?php _e( 'Cancel', 'gravityforms' ); ?></a>

	</form>

</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-text">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<input type="text" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="{{ data.value }}"/>
		<div style="padding:8px 0 0 0; font-size:11px; font-style:italic; color:#5A5A5A">{{ data.description }}</div>
	</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-url">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<input type="url" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="{{ data.value }}" class="code"/>
	</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-textarea">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<textarea name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}">{{ data.value }}</textarea>
	</div>

</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-select">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<select name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}">
			<# _.each( data.options, function( label, value ) { #>
				<option value="{{ value }}" <# if ( value == data.value ){ print('selected'); }; #> <# if (data.attr == 'id' && value == '') { print('disabled="disabled"')}; #>>{{ label }}</option>
			<# }); #>
		</select>
	</div>
	<div style="padding:8px 0 0 0; font-size:11px; font-style:italic; color:#5A5A5A">{{ data.description }}</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-radio">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}-{{ value }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<# _.each( data.options, function( label, value ) { #>
			<input id="gf-shortcode-attr-{{ data.attr }}-{{ value }}" type="radio" name="{{ data.attr }}" value="{{ value }}" <# if ( value == data.value ){ print('checked'); } #>>{{ label }}<br />
		<# }); #>
	</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-checkbox">
		<input type="checkbox" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="true" <# var val = ! data.value && data.default != undefined ? data.default : data.value; if ('true' == data.value ){ print('checked'); } #>>
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-email">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<input type="email" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="{{ data.value}}" />
	</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-number">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<input type="number" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="{{ data.value}}" />
	</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-hidden">
	<div class="field-block">
		<input type="hidden" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="true" />
	</div>
</script>

<script type="text/html" id="tmpl-gf-shortcode-ui-field-date">
	<div class="field-block">
		<label for="gf-shortcode-attr-{{ data.attr }}">{{ data.label }} <a href="#" onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_{{data.action}}_{{data.attr}}" title="{{data.tooltip}}"><i class='fa fa-question-circle'></i></a></label>
		<input type="date" name="{{ data.attr }}" id="gf-shortcode-attr-{{ data.attr }}" value="{{ data.value }}" />
	</div>
</script>