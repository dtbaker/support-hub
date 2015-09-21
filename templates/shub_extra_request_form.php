<style type="text/css">
	* {margin:0; padding:0;}

form {
	padding:1em 0 ;
}
fieldset {
	border:1px solid #ddd;
	padding:1px 20px 5px;
	margin: 0 0 1em;
}
fieldset > div {
	margin:1em 0;
	clear:both;
}
fieldset p{
	margin:1em 0;
}
label {
	float:left;
	width:10em;
	text-align:right;
	margin-right:1em;
}
legend {
	color:#0b77b7;
}
legend span {
	width:10em;
	text-align:right;
}
textarea,
input {
	padding:0.15em;
	border:1px solid #ddd;
	background:#fafafa;
	-moz-border-radius:0.4em;
	-khtml-border-radius:0.4em;
}
textarea:hover, textarea:focus,
input:hover, input:focus {
	border-color:#c5c5c5;
	background:#f6f6f6;
}
input.default {
	color:#bbb;
}

#submit-go {
	margin-top:1em;
	overflow:hidden;
	border:0;
	display:block;
	background: #0b77b7;
	color:#FFF;
    padding: 8px 10px;
}
a,a:link,a:visited{
	color:#0b77b7;
	font-weight: normal;
}
	.error{
		color:#FF0000;
	}
</style>

<form action="" method="post">
	<?php if(isset($_GET['done'])){ ?>
	<fieldset class="shub_extra_done">
		<legend>Thank You</legend>
		<div>
			Your extra information was received successfully.
		</div>
	</fieldset>
	<?php } ?>
	<?php if(isset($login_status) && is_array($login_status) && !empty($login_status['message']) && empty($extra_previous_data_errors)){ ?>
	<fieldset class="shub_extra_past_messages">
		<legend>Past Messages:</legend>
		<div>
			<?php echo $login_status['message'];?>
		</div>
	</fieldset>
	<?php } ?>
	<?php if(!isset($_GET['done'])){ ?>
		<?php if(count($extras)){ ?>
		<fieldset class="shub_extra_details">
			<legend>Please Provide Additional Details:</legend>
			<?php
			foreach($extras as $extra){
				if($extra->get('extra_description')){
					?>
					<div>
						<em><?php echo htmlspecialchars($extra->get('extra_description'));?></em>
						<br/>
					</div>
				<?php }
				if(isset($extra_previous_data_errors[$extra->get('shub_extra_id')])){
					?>
					<div class="error">
						<em><?php echo htmlspecialchars($extra_previous_data_errors[$extra->get('shub_extra_id')]);?></em>
						<br/>
					</div>
					<?php
				}
				?>
				<div>
					<label for="extra_<?php echo $extra->get('shub_extra_id');?>"><?php echo htmlspecialchars($extra->get('extra_name'));?>:</label>
					<?php shub_module_form::generate_form_element(array(
						'type' => $extra->get('field_type') ? $extra->get('field_type') : 'text',
						'name' => 'extra['.$extra->get('shub_extra_id').']',
						'value' => isset($extra_previous_data[$extra->get('shub_extra_id')]) ? $extra_previous_data[$extra->get('shub_extra_id')] : '',
					)); ?>
				</div>
				<?php
			} ?>
		</fieldset>
		<?php } ?>
	<fieldset class="shub_extra_notes">
		<legend>Any more information? (optional)</legend>
		<div>
			<label for="extra_notes">Message:</label>
			<?php shub_module_form::generate_form_element(array(
				'type' => 'textarea',
				'name' => 'extra_notes',
				'value' => isset($extra_previous_notes) ? $extra_previous_notes : '',
			)); ?>
		</div>
	</fieldset>
	<div><button type="submit" id="submit-go">Submit Private Message</button></div>
	<?php } ?>
</form>