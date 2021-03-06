{if $active_worker->is_superuser}
<div style="margin-bottom:5px;">
	<button id="btnWorkspaceTabAddWidget{$model->id}" type="button" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_WORKSPACE_WIDGET}" data-context-id="0" data-edit="tab:{$model->id}" data-width="75%"><span class="glyphicons glyphicons-circle-plus"></span> {'common.add'|devblocks_translate|capitalize}</button>
</div>
{/if}

{if 'sidebar_left' == $layout}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--sidebar-left" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="sidebar" class="cerb-workspace-layout-zone" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.sidebar item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
		
		<div data-layout-zone="content" class="cerb-workspace-layout-zone" style="flex:2 2 66%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.content item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{elseif 'sidebar_right' == $layout}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--sidebar-right" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="content" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--content" style="flex:2 2 66%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.content item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
		
		<div data-layout-zone="sidebar" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--sidebar" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.sidebar item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{else}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--content" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="content" class="cerb-workspace-layout-zone" style="flex:1 1 100%;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.content item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{/if}

<script type="text/javascript">
$(function() {
	var $container = $('#workspaceTab{$model->id}');
	var $add_button = $('#btnWorkspaceTabAddWidget{$model->id}');
	
	// Drag
	{if $active_worker->is_superuser}
	$container.find('.cerb-workspace-layout-zone--widgets')
		.sortable({
			tolerance: 'pointer',
			items: '.cerb-workspace-widget',
			helper: 'clone',
			placeholder: 'ui-state-highlight',
			forceHelperSize: true,
			forcePlaceholderSize: true,
			handle: '.cerb-workspace-widget--header .glyphicons-menu-hamburger',
			connectWith: '.cerb-workspace-layout-zone--widgets',
			opacity: 0.7,
			start: function(event, ui) {
				$container.find('.cerb-workspace-layout-zone--widgets')
					.css('border', '2px dashed orange')
					.css('background-color', 'rgb(250,250,250)')
					.css('min-height', '100vh')
					;
			},
			stop: function(event, ui) {
				$container.find('.cerb-workspace-layout-zone--widgets')
					.css('border', '')
					.css('background-color', '')
					.css('min-height', 'initial')
					;
			},
			//receive: function(e, ui) {},
			update: function(event, ui) {
				$container.trigger('cerb-reorder');
			}
		})
		;
	{/if}
	
	$container.on('cerb-reorder', function(e) {
		var results = { 'zones': { } };
		
		// Zones
		$container.find('> .cerb-workspace-layout-zone')
			.each(function(d) {
				var $cell = $(this);
				var zone = $cell.attr('data-layout-zone');
				var ids = $cell.find('.cerb-workspace-widget').map(function(d) { return $(this).attr('data-widget-id'); });
				
				results.zones[zone] = $.makeArray(ids);
			})
			;
		
		genericAjaxGet('', 'c=profiles&a=handleSectionAction&section=workspace_widget&action=reorderWidgets&tab_id={$model->id}' 
			+ '&' + $.param(results)
		);
	});
	
	$container.on('cerb-widget-refresh', function(e) {
		var widget_id = e.widget_id;
		var refresh_options = (e.refresh_options && typeof e.refresh_options == 'object') ? e.refresh_options : {};
		
		async.series([ async.apply(loadWidgetFunc, widget_id, false, refresh_options) ], function(err, json) {
			// Done
		});
	});
	
	var addEvents = function($target) {
		var $menu = $target.find('.cerb-workspace-widget--menu');
		var $menu_link = $target.find('.cerb-workspace-widget--link');
		
		$menu
			.menu({
				select: function(event, ui) {
					var $li = $(ui.item);
					$li.closest('ul').hide();
					
					if($li.is('.cerb-workspace-widget-menu--refresh')) {
						var $widget = $li.closest('.cerb-workspace-widget');
						
						async.series([ async.apply(loadWidgetFunc, $widget.attr('data-widget-id'), false, {}) ], function(err, json) {
							// Done
						});
					}
				}
			})
			;
		
		$menu_link.on('click', function(e) {
			e.stopPropagation();
			$(this).closest('.cerb-workspace-widget').find('.cerb-workspace-widget--menu').toggle();
		});
		
		$menu.find('.cerb-peek-trigger')
			.cerbPeekTrigger()
			.on('cerb-peek-saved', function(e) {
				// [TODO] Check the event type
				async.series([ async.apply(loadWidgetFunc, e.id, true, {}) ], function(err, json) {
					// Done
				});
			})
			.on('cerb-peek-deleted', function(e) {
				$('#workspaceWidget' + e.id).closest('.cerb-workspace-widget').remove();
				$container.trigger('cerb-reorder');
			})
			;
		
		return $target;
	}
	
	addEvents($container);
	
	var jobs = [];
	
	{if $active_worker->is_superuser}
	$add_button
		.cerbPeekTrigger()
		.on('cerb-peek-saved', function(e) {
			var $zone = $container.find('> .cerb-workspace-layout-zone:first > .cerb-workspace-layout-zone--widgets:first');
			var $placeholder = $('<div class="cerb-workspace-widget"/>').hide().prependTo($zone);
			var $widget = $('<div/>').attr('id', 'workspaceWidget' + e.id).appendTo($placeholder);
			
			async.series([ async.apply(loadWidgetFunc, e.id, true, {}) ], function(err, json) {
				$container.trigger('cerb-reorder');
			});
		})
		;
	{/if}
	
	var loadWidgetFunc = function(widget_id, is_full, refresh_options, callback) {
		var $widget = $('#workspaceWidget' + widget_id).empty();
		var $spinner = $('<span class="cerb-ajax-spinner"/>').appendTo($widget);
		
		var request_url = 'c=profiles&a=handleSectionAction&section=workspace_widget&action=renderWidget&id=' 
			+ encodeURIComponent(widget_id) 
			+ '&full=' + encodeURIComponent(is_full ? 1 : 0)
			;
		
		if(typeof refresh_options == 'object')
			request_url += '&' + $.param({ 'options': refresh_options });
		
		genericAjaxGet('', request_url, function(html) {
			if(0 == html.length) {
				$widget.empty();
				
			} else {
				try {
					if(is_full) {
						addEvents($(html)).insertBefore(
							$widget.attr('id',null).closest('.cerb-workspace-widget').hide()
						);
						
						$widget.closest('.cerb-workspace-widget').remove();
					} else {
						$widget.html(html);
					}
				} catch(e) {
					if(console)
						console.error(e);
				}
			}
			callback();
		});
	};
	
	{foreach from=$zones item=zone}
	{foreach from=$zone item=widget}
	jobs.push(
		async.apply(loadWidgetFunc, {$widget->id|default:0}, false, {})
	);
	{/foreach}
	{/foreach}
	
	async.parallelLimit(jobs, 2, function(err, json) {
		
	});
});
</script>