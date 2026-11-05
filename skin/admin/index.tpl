{$LANG.ip2location_country_blocker.module_help}
<div id="{$GENERAL_TAB_ID}" class="tab_content">
	<h3>{$TITLE}</h3>

	<form action="{$VAL_SELF}" method="post" id="module_form">
		{if !$DB_OK}
		<div class="alert alert-error">
			<p><strong>{$LANG.ip2location_country_blocker.error_db_missing_title}</strong></p>
			<p>{$LANG.ip2location_country_blocker.error_db_missing_text}</p>
			<p><a href="{$DB_LITE_URL}" target="_blank">{$LANG.ip2location_country_blocker.download_db_link}</a></p>
		</div>
		{/if}

		<fieldset>
			<legend>{$LANG.ip2location_country_blocker.section_general}</legend>
			<div>
				<label for="status">{$LANG.common.status}</label>
				<span><input type="hidden" name="module[status]" id="status" class="toggle" value="{$MODULE.status}" /></span>
			</div>
			<div>
				<label for="lookup_source">{$LANG.ip2location_country_blocker.lookup_source}</label>
				<span>
					<select name="module[lookup_source]" id="lookup_source" class="textbox">
						<option value="bin" {$SELECT_lookup_source_bin}>{$LANG.ip2location_country_blocker.lookup_source_bin}</option>
						<option value="api" {$SELECT_lookup_source_api}>{$LANG.ip2location_country_blocker.lookup_source_api}</option>
					</select>
				</span>
			</div>
			<div id="row_api_key">
				<label for="api_key">{$LANG.ip2location_country_blocker.api_key}</label>
				<span><input type="text" name="module[api_key]" id="api_key" class="textbox" value="{$MODULE.api_key}" autocomplete="off" /></span>
				<span class="sub">{$LANG.ip2location_country_blocker.api_key_description}</span>
			</div>
			<div id="row_db_path">
				<label for="db_path">{$LANG.ip2location_country_blocker.db_path}</label>
				<span><input type="text" name="module[db_path]" id="db_path" class="textbox" value="{$DB_PATH}" /></span>
				<span class="sub">{$LANG.ip2location_country_blocker.db_path_description}
					{if $DB_OK}<strong class="success">{$LANG.ip2location_country_blocker.db_ok}</strong>{/if}
				</span>
			</div>
			<div>
				<label for="use_xff">{$LANG.ip2location_country_blocker.use_xff}</label>
				<span><input type="checkbox" name="module[use_xff]" id="use_xff" value="1" {$CHECKED_use_xff_1} /></span>
				<span class="sub">{$LANG.ip2location_country_blocker.use_xff_description}</span>
			</div>
		</fieldset>

		<fieldset>
			<legend>{$LANG.ip2location_country_blocker.section_countries}</legend>
			<div>
				<label for="blocked_countries">{$LANG.ip2location_country_blocker.blocked_countries}</label>
				<span>
					<select name="module[blocked_countries][]" id="blocked_countries" class="textbox" multiple size="12">
						{foreach from=$COUNTRIES item=country}
						<option value="{$country.iso}"{if $country.selected} selected="selected"{/if}>{$country.name} ({$country.iso})</option>
						{/foreach}
					</select>
				</span>
				<span class="sub">{$LANG.ip2location_country_blocker.blocked_countries_description}
					{if $BLOCKED_COUNT > 0}<strong>{$BLOCKED_COUNT} {$LANG.ip2location_country_blocker.selected_count}</strong>{/if}
				</span>
			</div>
		</fieldset>

		<fieldset>
			<legend>{$LANG.ip2location_country_blocker.section_action}</legend>
			<div>
				<label for="module_action">{$LANG.ip2location_country_blocker.action}</label>
				<span>
					<select name="module[action]" id="module_action" class="textbox">
						<option value="403" {$SELECT_action_403}>{$LANG.ip2location_country_blocker.action_403}</option>
						<option value="page" {$SELECT_action_page}>{$LANG.ip2location_country_blocker.action_page}</option>
						<option value="redirect" {$SELECT_action_redirect}>{$LANG.ip2location_country_blocker.action_redirect}</option>
					</select>
				</span>
			</div>
			<div id="sec_403">
				<label for="message_403">{$LANG.ip2location_country_blocker.message_403}</label>
				<span><textarea name="module[message_403]" id="message_403" class="textbox" rows="3" cols="60">{$MODULE.message_403}</textarea></span>
			</div>
			<div id="sec_page">
				<label for="custom_page">{$LANG.ip2location_country_blocker.custom_page}</label>
				<span><textarea name="module[custom_page]" id="custom_page" class="textbox" rows="10" cols="60">{$MODULE.custom_page}</textarea></span>
			</div>
			<div id="sec_redirect">
				<label for="redirect_url">{$LANG.ip2location_country_blocker.redirect_url}</label>
				<span><input type="text" name="module[redirect_url]" id="redirect_url" class="textbox" value="{$MODULE.redirect_url}" /></span>
				<span class="sub">{$LANG.ip2location_country_blocker.redirect_url_description}</span>
			</div>
		</fieldset>

		<fieldset>
			<legend>{$LANG.ip2location_country_blocker.section_test}</legend>
			<div>
				<label>{$LANG.ip2location_country_blocker.your_ip}</label>
				<span><strong>{$TEST_IP}</strong></span>
			</div>
			<div>
				<label>{$LANG.ip2location_country_blocker.detected_country}</label>
				<span><strong>{$TEST_COUNTRY}</strong></span>
			</div>
		</fieldset>

		<div class="form_control">
			<input type="submit" name="save" value="{$LANG.common.save}" class="button_primary" />
		</div>
	</form>

	<form action="{$VAL_SELF}" method="post" id="download_form">
		<fieldset id="sec_download">
			<legend>{$LANG.ip2location_country_blocker.section_database}</legend>
			<div>
				<label for="download_token">{$LANG.ip2location_country_blocker.download_token}</label>
				<span><input type="text" name="download_token" id="download_token" class="textbox" value="{$MODULE.download_token}" autocomplete="off" /></span>
				<span class="sub">{$LANG.ip2location_country_blocker.download_token_description}</span>
			</div>
			<div>
				<label for="db_file_code">{$LANG.ip2location_country_blocker.db_file_code}</label>
				<span>
					<select name="db_file_code" id="db_file_code" class="textbox">
						<option value="DB1BINIPV6" {$SELECT_db_file_code_DB1BINIPV6}>{$LANG.ip2location_country_blocker.option_db1bin}</option>
						<option value="DB1LITEBINIPV6" {$SELECT_db_file_code_DB1LITEBINIPV6}>{$LANG.ip2location_country_blocker.option_db1litebin}</option>
					</select>
				</span>
				<span class="sub">{$LANG.ip2location_country_blocker.db_file_code_description}</span>
			</div>
			<div>
				<label class="spacer">&nbsp;</label>
				<span><input type="submit" name="download_db" value="{$LANG.ip2location_country_blocker.download_db}" class="button_primary" style="margin: 10px 0;" /></span>
			</div>
		</fieldset>
	</form>
</div>

<script type="text/javascript">
// Toggle the action-specific fields based on the selected block action
jQuery(function($) {
	function showAction() {
		var v = $('#module_action').val();
		$('#sec_403').toggle(v === '403');
		$('#sec_page').toggle(v === 'page');
		$('#sec_redirect').toggle(v === 'redirect');
	}
	// Show the fields relevant to the selected lookup source
	function showLookupSource() {
		var v = $('#lookup_source').val();
		$('#row_api_key').toggle(v === 'api');
		$('#row_db_path').toggle(v === 'bin');
		$('#sec_download').toggle(v === 'bin');
	}
	$('#module_action').on('change', showAction);
	showAction();
	$('#lookup_source').on('change', showLookupSource);
	showLookupSource();
});
</script>
