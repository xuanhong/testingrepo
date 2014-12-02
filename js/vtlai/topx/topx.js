/*======================================================================*\
|| #################################################################### ||
|| # vt.Lai TopX 1.4 For XenForo  by SinhVienIT.net                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2013 Vu Thanh Lai. All Rights Reserved.               # ||
|| # Please do not remove this comment lines.                         # ||
|| # -------------------- LAST MODIFY INFOMATION -------------------- # ||
|| # Last Modify: 08-08-2013 11:00:00 PM by: Vu Thanh Lai             # ||
|| # Please do not remove these comment line if use my code or a part # ||
|| #################################################################### ||
\*======================================================================*/

	var TOPX_SIZE='small';
	var TOPX_SERVER_TIME=0;
	
	var TOPX_WRAPPER_SELECTOR='#vtlai-topx';
	var TOPX_SELECTOR='#vtlai-topx .widget-topx';
	var TOPX_TAB_SELECTOR='#vtlai-topx ul.topx-tabs .sub-tab';
	var TOPX_REFRESH_SELECTOR='#vtlai-topx #refresh';
	var TOPX_COUNT_SELECTOR='#vtlai-topx #vtlai-topx-result-count';
	var TOPX_RESIZE_SELECTOR='#vtlai-topx ul.topx-tabs #full-list';
	var TOPX_RIGHTBOX_SELECTOR='#vtlai-topx .widget-ad-300';
	var TOPX_CONTENTBOX_SELECTOR='#vtlai-topx .topx-content';
	var TOPX_THREAD_SELECTOR='#vtlai-topx .topx-content .list-link-title a';
	
	var TOPX_FULL_WIDTH=0;
	var TOPX_SMALL_WIDTH=0;
	var TOPX_RESULT_COUNT=15;
	var TOPX_RELOAD_SECOND=30;
	var TOPX_FORUMIDS='all';
	
	var TOPX_FUNC_RELOAD=null;
	$(document).ready(function() {
		TOPX_FULL_WIDTH=jQuery(TOPX_WRAPPER_SELECTOR).innerWidth();
		TOPX_SMALL_WIDTH=jQuery(TOPX_SELECTOR).width();
		TOPX_FUNC_RELOAD=function(setInterv){
			jQuery.ajax({
				'url': 'vtlaitopx/?_xfNoRedirect=1&_xfResponseType=json',
				'data': {'forumids':TOPX_FORUMIDS,'resultCount':TOPX_RESULT_COUNT,'_xfToken':XenForo._csrfToken},
				'type': 'POST'
			}).done(function(data){
				jQuery(TOPX_CONTENTBOX_SELECTOR).html(data.templateHtml);
				XenForo.activate(TOPX_CONTENTBOX_SELECTOR);
				if(typeof TOPX_FUNC_TOOLTIP == 'function')
				{
					TOPX_FUNC_TOOLTIP();
				}
			}).fail(function(){
				console.error('Lỗi TopX phía server !');
			});
			if(typeof setInterv!='undefined' && setInterv)
				setInterval("TOPX_FUNC_RELOAD()",TOPX_RELOAD_SECOND*1000);
		}
		
		$(TOPX_TAB_SELECTOR).click(function() {
			jQuery(TOPX_TAB_SELECTOR).removeClass('active');
			jQuery(this).addClass('active'); 
			TOPX_FORUMIDS=jQuery(this).attr('data-forumids');
			TOPX_FUNC_RELOAD();
		});
		$(TOPX_REFRESH_SELECTOR).click(function() {
			TOPX_FUNC_RELOAD();
		});
		$(TOPX_COUNT_SELECTOR).change(function(){
			TOPX_RESULT_COUNT=jQuery(this).val();
			TOPX_FUNC_RELOAD();
		});
		$(TOPX_RESIZE_SELECTOR).click(function(){
			if(TOPX_SIZE=='small')
			{
				$(TOPX_RIGHTBOX_SELECTOR).fadeOut('fast',function(){
					$(TOPX_SELECTOR).animate({'width':TOPX_FULL_WIDTH},function(){
						TOPX_SIZE='large';
						jQuery(TOPX_WRAPPER_SELECTOR).removeClass('small');
						jQuery(TOPX_WRAPPER_SELECTOR).addClass('large');
					});
				});
			}
			else
			{
				jQuery(TOPX_WRAPPER_SELECTOR).removeClass('large');
				jQuery(TOPX_WRAPPER_SELECTOR).addClass('small');
				$(TOPX_SELECTOR).animate({'width':TOPX_SMALL_WIDTH},function(){
					$(TOPX_RIGHTBOX_SELECTOR).fadeIn('fast',function(){
						TOPX_SIZE='small';
						
					});
				});
			}
		});
		
		TOPX_FUNC_RELOAD(1);
	});