<b>{'common.query'|devblocks_translate|capitalize}:</b>
<div style="margin-left:10px;margin-bottom:10px;">
	<textarea name="{$namePrefix}[query]" class="placeholders">{$params.query}</textarea>
</div>

<b>Save result to a placeholder named:</b><br>
<div style="margin-left:10px;margin-bottom:10px;">
	&#123;&#123;<input type="text" name="{$namePrefix}[object_placeholder]" value="{$params.object_placeholder|default:"_results"}" required="required" spellcheck="false" size="32" placeholder="e.g. _results">&#125;&#125;
</div>

<script type="text/javascript">
var $action = $('#{$namePrefix}_{$nonce}');
</script>
