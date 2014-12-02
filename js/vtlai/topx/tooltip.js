/*======================================================================*\
|| #################################################################### ||
|| # vt.Lai TopX 1.4 For XenForo  by SinhVienIT.net                   # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2013 Vu Thanh Lai. All Rights Reserved.               # ||
|| # Please do not remove this comment lines.                         # ||
|| # -------------------- LAST MODIFY INFOMATION -------------------- # ||
|| # Last Modify: 13-08-2013 07:00:00 PM by: Vu Thanh Lai             # ||
|| # Please do not remove these comment line if use my code or a part # ||
|| #################################################################### ||
\*======================================================================*/

var TOPX_TOOLTIP_MARGIN=0;

var TOPX_FUNC_TOOLTIP=function(){
	if(jQuery('#vtlai-topx-tooltip').length==0)
	{
		jQuery('body').append('<div id="vtlai-topx-tooltip" class="xenTooltip vtlaiTooltip" style="position: absolute;">\
			<div class="tooltipContent"></div>\
			<span class="arrow"></span>\
		</div>');
	}
	jQuery('#vtlai-topx .tooltip').hover(function(e){
		var html='';
		var title=jQuery(this).attr('data-title');
		if(typeof title=='string')
		{
			html='<b>'+title+'</b>';
			var views=jQuery(this).attr('data-views');
			var reply=jQuery(this).attr('data-reply');
			var like=jQuery(this).attr('data-like');
			var owner=jQuery(this).attr('data-owner');
			if(typeof views=='string' && typeof reply=='string' && typeof like=='string' && typeof owner=='string')
			{
				html+='<br />'+'Người tạo: '+owner;
				html+='<br />'+'Lượt xem: '+views+', Trả lời: '+reply+', Like: '+like;
			}
			jQuery('#vtlai-topx-tooltip .tooltipContent').html(html);
			jQuery('#vtlai-topx-tooltip').show();
		}
	},function(){
		jQuery('#vtlai-topx-tooltip').hide();
	}).mousemove(function(e){
		var pageX=e.pageX;
		var pageY=e.pageY;
		var h=jQuery('#vtlai-topx-tooltip').height();
		var w=jQuery('#vtlai-topx-tooltip').width();
		if(h!=null && w!=null)
		{
			var left=pageX-15+TOPX_TOOLTIP_MARGIN;
			var top=pageY-h-20-TOPX_TOOLTIP_MARGIN;
			jQuery('#vtlai-topx-tooltip').css({'top':top+'px','left':left+'px'});
		}
	});
}
