(function(a){function d(){url=location.href;return hashtag=url.indexOf("#!")!=-1?decodeURI(url.substring(url.indexOf("#!")+2,url.length)):!1}function e(a,d){var a=a.replace(/[\[]/,"\\[").replace(/[\]]/,"\\]"),e=RegExp("[\\?&]"+a+"=([^&#]*)").exec(d);return e==null?"":e[1]}a.prettyPhoto={version:"3.1.3"};a.fn.prettyPhoto=function(f){function n(){a(".pp_loaderIcon").hide();projectedTop=scroll_pos.scrollTop+(g/2-b.containerHeight/2);projectedTop<0&&(projectedTop=0);$ppt.fadeTo(settings.animation_speed,
1);$pp_pic_holder.find(".pp_content").animate({height:b.contentHeight,width:b.contentWidth},settings.animation_speed);$pp_pic_holder.animate({top:projectedTop,left:i/2-b.containerWidth/2,width:b.containerWidth},settings.animation_speed,function(){$pp_pic_holder.find(".pp_hoverContainer,#fullResImage").height(b.height).width(b.width);$pp_pic_holder.find(".pp_fade").fadeIn(settings.animation_speed);isSet&&o(pp_images[set_position])=="image"?$pp_pic_holder.find(".pp_hoverContainer").show():$pp_pic_holder.find(".pp_hoverContainer").hide();
b.resized?a("a.pp_expand,a.pp_contract").show():a("a.pp_expand").hide();settings.autoplay_slideshow&&!l&&!p&&a.prettyPhoto.startSlideshow();settings.changepicturecallback();p=!0});isSet&&settings.overlay_gallery&&o(pp_images[set_position])=="image"&&settings.ie6_fallback&&!(a.browser.msie&&parseInt(a.browser.version)==6)?(itemWidth=57,navWidth=settings.theme=="facebook"||settings.theme=="pp_default"?50:30,itemsPerPage=Math.floor((b.containerWidth-100-navWidth)/itemWidth),itemsPerPage=itemsPerPage<
pp_images.length?itemsPerPage:pp_images.length,totalPage=Math.ceil(pp_images.length/itemsPerPage)-1,totalPage==0?(navWidth=0,$pp_gallery.find(".pp_arrow_next,.pp_arrow_previous").hide()):$pp_gallery.find(".pp_arrow_next,.pp_arrow_previous").show(),galleryWidth=itemsPerPage*itemWidth,fullGalleryWidth=pp_images.length*itemWidth,$pp_gallery.css("margin-left",-(galleryWidth/2+navWidth/2)).find("div:first").width(galleryWidth+5).find("ul").width(fullGalleryWidth).find("li.selected").removeClass("selected"),
goToPage=Math.floor(set_position/itemsPerPage)<totalPage?Math.floor(set_position/itemsPerPage):totalPage,a.prettyPhoto.changeGalleryPage(goToPage),$pp_gallery_li.filter(":eq("+set_position+")").addClass("selected")):$pp_pic_holder.find(".pp_content").unbind("mouseenter mouseleave")}function t(c){$pp_pic_holder.find("#pp_full_res object,#pp_full_res embed").css("visibility","hidden");$pp_pic_holder.find(".pp_fade").fadeOut(settings.animation_speed,function(){a(".pp_loaderIcon").show();c()})}function y(c){c>
1?a(".pp_nav").show():a(".pp_nav").hide()}function h(a,b){resized=!1;u(a,b);imageWidth=a;imageHeight=b;if((j>i||k>g)&&doresize&&settings.allow_resize&&!m){for(resized=!0,fitting=!1;!fitting;)j>i?(imageWidth=i-200,imageHeight=b/a*imageWidth):k>g?(imageHeight=g-200,imageWidth=a/b*imageHeight):fitting=!0,k=imageHeight,j=imageWidth;u(imageWidth,imageHeight);(j>i||k>g)&&h(j,k)}return{width:Math.floor(imageWidth),height:Math.floor(imageHeight),containerHeight:Math.floor(k),containerWidth:Math.floor(j)+
settings.horizontal_padding*2,contentHeight:Math.floor(q),contentWidth:Math.floor(v),resized:resized}}function u(c,b){c=parseFloat(c);b=parseFloat(b);$pp_details=$pp_pic_holder.find(".pp_details");$pp_details.width(c);detailsHeight=parseFloat($pp_details.css("marginTop"))+parseFloat($pp_details.css("marginBottom"));$pp_details=$pp_details.clone().addClass(settings.theme).width(c).appendTo(a("body")).css({position:"absolute",top:-1E4});detailsHeight+=$pp_details.height();detailsHeight=detailsHeight<=
34?36:detailsHeight;a.browser.msie&&a.browser.version==7&&(detailsHeight+=8);$pp_details.remove();$pp_title=$pp_pic_holder.find(".ppt");$pp_title.width(c);titleHeight=parseFloat($pp_title.css("marginTop"))+parseFloat($pp_title.css("marginBottom"));$pp_title=$pp_title.clone().appendTo(a("body")).css({position:"absolute",top:-1E4});titleHeight+=$pp_title.height();$pp_title.remove();q=b+detailsHeight;v=c;k=q+titleHeight+$pp_pic_holder.find(".pp_top").height()+$pp_pic_holder.find(".pp_bottom").height();
j=c}function o(a){return a.match(/youtube\.com\/watch/i)||a.match(/youtu\.be/i)?"youtube":a.match(/vimeo\.com/i)?"vimeo":a.match(/\b.mov\b/i)?"quicktime":a.match(/\b.swf\b/i)?"flash":a.match(/\biframe=true\b/i)?"iframe":a.match(/\bajax=true\b/i)?"ajax":a.match(/\bcustom=true\b/i)?"custom":a.substr(0,1)=="#"?"inline":"image"}function r(){doresize&&typeof $pp_pic_holder!="undefined"&&(scroll_pos=w(),contentHeight=$pp_pic_holder.height(),contentwidth=$pp_pic_holder.width(),projectedTop=g/2+scroll_pos.scrollTop-
contentHeight/2,projectedTop<0&&(projectedTop=0),contentHeight>g||$pp_pic_holder.css({top:projectedTop,left:i/2+scroll_pos.scrollLeft-contentwidth/2}))}function w(){if(self.pageYOffset)return{scrollTop:self.pageYOffset,scrollLeft:self.pageXOffset};else if(document.documentElement&&document.documentElement.scrollTop)return{scrollTop:document.documentElement.scrollTop,scrollLeft:document.documentElement.scrollLeft};else if(document.body)return{scrollTop:document.body.scrollTop,scrollLeft:document.body.scrollLeft}}
function x(){settings.social_tools&&(facebook_like_link=settings.social_tools.replace("{location_href}",encodeURIComponent(location.href)));settings.markup=settings.markup.replace("{pp_social}",settings.social_tools?facebook_like_link:"");a("body").append(settings.markup);$pp_pic_holder=a(".pp_pic_holder");$ppt=a(".ppt");$pp_overlay=a("div.pp_overlay");if(isSet&&settings.overlay_gallery){currentGalleryPage=0;toInject="";for(var c=0;c<pp_images.length;c++)pp_images[c].match(/\b(jpg|jpeg|png|gif)\b/gi)?
(classname="",img_src=pp_images[c]):(classname="default",img_src=""),toInject+="<li class='"+classname+"'><a href='#'><img src='"+img_src+"' width='50' alt='' /></a></li>";toInject=settings.gallery_markup.replace(/{gallery}/g,toInject);$pp_pic_holder.find("#pp_full_res").after(toInject);$pp_gallery=a(".pp_pic_holder .pp_gallery");$pp_gallery_li=$pp_gallery.find("li");$pp_gallery.find(".pp_arrow_next").click(function(){a.prettyPhoto.changeGalleryPage("next");a.prettyPhoto.stopSlideshow();return!1});
$pp_gallery.find(".pp_arrow_previous").click(function(){a.prettyPhoto.changeGalleryPage("previous");a.prettyPhoto.stopSlideshow();return!1});$pp_pic_holder.find(".pp_content").hover(function(){$pp_pic_holder.find(".pp_gallery:not(.disabled)").fadeIn()},function(){$pp_pic_holder.find(".pp_gallery:not(.disabled)").fadeOut()});itemWidth=57;$pp_gallery_li.each(function(c){a(this).find("a").click(function(){a.prettyPhoto.changePage(c);a.prettyPhoto.stopSlideshow();return!1})})}settings.slideshow&&($pp_pic_holder.find(".pp_nav").prepend('<a href="#" class="pp_play">Play</a>'),
$pp_pic_holder.find(".pp_nav .pp_play").click(function(){a.prettyPhoto.startSlideshow();return!1}));$pp_pic_holder.attr("class","pp_pic_holder "+settings.theme);$pp_overlay.css({opacity:0,height:a(document).height(),width:a(window).width()}).bind("click",function(){settings.modal||a.prettyPhoto.close()});a("a.pp_close").bind("click",function(){a.prettyPhoto.close();return!1});a("a.pp_expand").bind("click",function(){a(this).hasClass("pp_expand")?(a(this).removeClass("pp_expand").addClass("pp_contract"),
doresize=!1):(a(this).removeClass("pp_contract").addClass("pp_expand"),doresize=!0);t(function(){a.prettyPhoto.open()});return!1});$pp_pic_holder.find(".pp_previous, .pp_nav .pp_arrow_previous").bind("click",function(){a.prettyPhoto.changePage("previous");a.prettyPhoto.stopSlideshow();return!1});$pp_pic_holder.find(".pp_next, .pp_nav .pp_arrow_next").bind("click",function(){a.prettyPhoto.changePage("next");a.prettyPhoto.stopSlideshow();return!1});r()}var f=jQuery.extend({animation_speed:"fast",slideshow:5E3,
autoplay_slideshow:!1,opacity:0.8,show_title:!1,allow_resize:!0,default_width:500,default_height:344,counter_separator_label:"/",theme:"pp_default",horizontal_padding:20,hideflash:!1,wmode:"opaque",autoplay:!0,modal:!1,deeplinking:!0,overlay_gallery:!0,keyboard_shortcuts:!0,changepicturecallback:function(){},callback:function(){},ie6_fallback:!0,markup:'<div class="pp_pic_holder"> <div class="ppt">&nbsp;</div> <div class="pp_top"> <div class="pp_left"></div> <div class="pp_middle"></div> <div class="pp_right"></div> </div> <div class="pp_content_container"> <div class="pp_left"> <div class="pp_right"> <div class="pp_content"> <div class="pp_loaderIcon"></div> <div class="pp_fade"> <a href="#" class="pp_expand" title="Expand the image">Expand</a> <div class="pp_hoverContainer"> <a class="pp_next" href="#">next</a> <a class="pp_previous" href="#">previous</a> </div> <div id="pp_full_res"></div> <div class="pp_details"> <div class="pp_nav"> <a href="#" class="pp_arrow_previous">Previous</a> <p class="currentTextHolder">0/0</p> <a href="#" class="pp_arrow_next">Next</a> </div> <p class="pp_description"></p> <div class="pp_social">{pp_social}</div> <a class="pp_close" href="#">Close</a> </div> </div> </div> </div> </div> </div> <div class="pp_bottom"> <div class="pp_left"></div> <div class="pp_middle"></div> <div class="pp_right"></div> </div> </div> <div class="pp_overlay"></div>',
gallery_markup:'<div class="pp_gallery"> <a href="#" class="pp_arrow_previous">Previous</a> <div> <ul> {gallery} </ul> </div> <a href="#" class="pp_arrow_next">Next</a> </div>',image_markup:'<img id="fullResImage" src="{path}" />',flash_markup:'<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="{width}" height="{height}"><param name="wmode" value="{wmode}" /><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="{path}" /><embed src="{path}" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="{width}" height="{height}" wmode="{wmode}"></embed></object>',
quicktime_markup:'<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" codebase="http://www.apple.com/qtactivex/qtplugin.cab" height="{height}" width="{width}"><param name="src" value="{path}"><param name="autoplay" value="{autoplay}"><param name="type" value="video/quicktime"><embed src="{path}" height="{height}" width="{width}" autoplay="{autoplay}" type="video/quicktime" pluginspage="http://www.apple.com/quicktime/download/"></embed></object>',iframe_markup:'<iframe src ="{path}" width="{width}" height="{height}" frameborder="no"></iframe>',
inline_markup:'<div class="pp_inline">{content}</div>',custom_markup:"",social_tools:'<div class="twitter"><a href="http://twitter.com/share" class="twitter-share-button" data-count="none">Tweet</a><script type="text/javascript" src="http://platform.twitter.com/widgets.js"><\/script></div><div class="facebook"><iframe src="http://www.facebook.com/plugins/like.php?locale=en_US&href={location_href}&amp;layout=button_count&amp;show_faces=true&amp;width=500&amp;action=like&amp;font&amp;colorscheme=light&amp;height=23" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:500px; height:23px;" allowTransparency="true"></iframe></div>'},
f),s=this,m=!1,b,p,q,v,k,j,g=a(window).height(),i=a(window).width(),l;doresize=!0;scroll_pos=w();a(window).unbind("resize.prettyphoto").bind("resize.prettyphoto",function(){r();g=a(window).height();i=a(window).width();typeof $pp_overlay!="undefined"&&$pp_overlay.height(a(document).height()).width(i)});f.keyboard_shortcuts&&a(document).unbind("keydown.prettyphoto").bind("keydown.prettyphoto",function(c){if(typeof $pp_pic_holder!="undefined"&&$pp_pic_holder.is(":visible"))switch(c.keyCode){case 37:a.prettyPhoto.changePage("previous");
c.preventDefault();break;case 39:a.prettyPhoto.changePage("next");c.preventDefault();break;case 27:settings.modal||a.prettyPhoto.close(),c.preventDefault()}});a.prettyPhoto.initialize=function(){settings=f;if(settings.theme=="pp_default")settings.horizontal_padding=16;if(settings.ie6_fallback&&a.browser.msie&&parseInt(a.browser.version)==6)settings.theme="light_square";theRel=a(this).attr("rel");galleryRegExp=/\[(?:.*)\]/;pp_images=(isSet=galleryRegExp.exec(theRel)?!0:!1)?jQuery.map(s,function(c){if(a(c).attr("rel").indexOf(theRel)!=
-1)return a(c).attr("href")}):a.makeArray(a(this).attr("href"));pp_titles=isSet?jQuery.map(s,function(c){if(a(c).attr("rel").indexOf(theRel)!=-1)return a(c).find("img").attr("alt")?a(c).find("img").attr("alt"):""}):a.makeArray(a(this).find("img").attr("alt"));pp_descriptions=isSet?jQuery.map(s,function(c){if(a(c).attr("rel").indexOf(theRel)!=-1)return a(c).attr("title")?a(c).attr("title"):""}):a.makeArray(a(this).attr("title"));if(pp_images.length>30)settings.overlay_gallery=!1;set_position=jQuery.inArray(a(this).attr("href"),
pp_images);rel_index=isSet?set_position:a("a[rel^='"+theRel+"']").index(a(this));x(this);settings.allow_resize&&a(window).bind("scroll.prettyphoto",function(){r()});a.prettyPhoto.open();return!1};a.prettyPhoto.open=function(c,d,g){if(typeof settings=="undefined"){settings=f;if(a.browser.msie&&a.browser.version==6)settings.theme="light_square";pp_images=a.makeArray(c);pp_titles=d?a.makeArray(d):a.makeArray("");pp_descriptions=g?a.makeArray(g):a.makeArray("");isSet=pp_images.length>1?!0:!1;set_position=
0;x(c.target)}a.browser.msie&&a.browser.version==6&&a("select").css("visibility","hidden");settings.hideflash&&a("object,embed,iframe[src*=youtube],iframe[src*=vimeo]").css("visibility","hidden");y(a(pp_images).size());a(".pp_loaderIcon").show();if(settings.deeplinking&&typeof theRel!="undefined")location.hash="!"+theRel+"/"+rel_index+"/";settings.social_tools&&(facebook_like_link=settings.social_tools.replace("{location_href}",encodeURIComponent(location.href)),$pp_pic_holder.find(".pp_social").html(facebook_like_link));
$ppt.is(":hidden")&&$ppt.css("opacity",0).show();$pp_overlay.show().fadeTo(settings.animation_speed,settings.opacity);$pp_pic_holder.find(".currentTextHolder").text(set_position+1+settings.counter_separator_label+a(pp_images).size());movie_width=parseFloat(e("width",pp_images[set_position]))?e("width",pp_images[set_position]):settings.default_width.toString();movie_height=parseFloat(e("height",pp_images[set_position]))?e("height",pp_images[set_position]):settings.default_height.toString();m=!1;movie_height.indexOf("%")!=
-1&&(movie_height=parseFloat(a(window).height()*parseFloat(movie_height)/100-150),m=!0);movie_width.indexOf("%")!=-1&&(movie_width=parseFloat(a(window).width()*parseFloat(movie_width)/100-150),m=!0);$pp_pic_holder.fadeIn(function(){settings.show_title&&pp_titles[set_position]!=""&&typeof pp_titles[set_position]!="undefined"?$ppt.html(unescape(pp_titles[set_position])):$ppt.html("&nbsp;");imgPreloader="";skipInjection=!1;switch(o(pp_images[set_position])){case "image":imgPreloader=new Image;nextImage=
new Image;if(isSet&&set_position<a(pp_images).size()-1)nextImage.src=pp_images[set_position+1];prevImage=new Image;if(isSet&&pp_images[set_position-1])prevImage.src=pp_images[set_position-1];$pp_pic_holder.find("#pp_full_res")[0].innerHTML=settings.image_markup.replace(/{path}/g,pp_images[set_position]);imgPreloader.onload=function(){b=h(imgPreloader.width,imgPreloader.height);n()};imgPreloader.onerror=function(){alert("Image cannot be loaded. Make sure the path is correct and image exist.");a.prettyPhoto.close()};
imgPreloader.src=pp_images[set_position];break;case "youtube":b=h(movie_width,movie_height);movie_id=e("v",pp_images[set_position]);movie_id==""&&(movie_id=pp_images[set_position].split("youtu.be/"),movie_id=movie_id[1],movie_id.indexOf("?")>0&&(movie_id=movie_id.substr(0,movie_id.indexOf("?"))),movie_id.indexOf("&")>0&&(movie_id=movie_id.substr(0,movie_id.indexOf("&"))));movie="http://www.youtube.com/embed/"+movie_id;e("rel",pp_images[set_position])?movie+="?rel="+e("rel",pp_images[set_position]):
movie+="?rel=1";settings.autoplay&&(movie+="&autoplay=1");toInject=settings.iframe_markup.replace(/{width}/g,b.width).replace(/{height}/g,b.height).replace(/{wmode}/g,settings.wmode).replace(/{path}/g,movie);break;case "vimeo":b=h(movie_width,movie_height);movie_id=pp_images[set_position];movie="http://player.vimeo.com/video/"+movie_id.match(/http:\/\/(www\.)?vimeo.com\/(\d+)/)[2]+"?title=0&amp;byline=0&amp;portrait=0";settings.autoplay&&(movie+="&autoplay=1;");vimeo_width=b.width+"/embed/?moog_width="+
b.width;toInject=settings.iframe_markup.replace(/{width}/g,vimeo_width).replace(/{height}/g,b.height).replace(/{path}/g,movie);break;case "quicktime":b=h(movie_width,movie_height);b.height+=15;b.contentHeight+=15;b.containerHeight+=15;toInject=settings.quicktime_markup.replace(/{width}/g,b.width).replace(/{height}/g,b.height).replace(/{wmode}/g,settings.wmode).replace(/{path}/g,pp_images[set_position]).replace(/{autoplay}/g,settings.autoplay);break;case "flash":b=h(movie_width,movie_height);flash_vars=
pp_images[set_position];flash_vars=flash_vars.substring(pp_images[set_position].indexOf("flashvars")+10,pp_images[set_position].length);filename=pp_images[set_position];filename=filename.substring(0,filename.indexOf("?"));toInject=settings.flash_markup.replace(/{width}/g,b.width).replace(/{height}/g,b.height).replace(/{wmode}/g,settings.wmode).replace(/{path}/g,filename+"?"+flash_vars);break;case "iframe":b=h(movie_width,movie_height);frame_url=pp_images[set_position];frame_url=frame_url.substr(0,
frame_url.indexOf("iframe")-1);toInject=settings.iframe_markup.replace(/{width}/g,b.width).replace(/{height}/g,b.height).replace(/{path}/g,frame_url);break;case "ajax":doresize=!1;b=h(movie_width,movie_height);skipInjection=doresize=!0;a.get(pp_images[set_position],function(a){toInject=settings.inline_markup.replace(/{content}/g,a);$pp_pic_holder.find("#pp_full_res")[0].innerHTML=toInject;n()});break;case "custom":b=h(movie_width,movie_height);toInject=settings.custom_markup;break;case "inline":myClone=
a(pp_images[set_position]).clone().append('<br clear="all" />').css({width:settings.default_width}).wrapInner('<div id="pp_full_res"><div class="pp_inline"></div></div>').appendTo(a("body")).show(),doresize=!1,b=h(a(myClone).width(),a(myClone).height()),doresize=!0,a(myClone).remove(),toInject=settings.inline_markup.replace(/{content}/g,a(pp_images[set_position]).html())}if(!imgPreloader&&!skipInjection)$pp_pic_holder.find("#pp_full_res")[0].innerHTML=toInject,n()});return!1};a.prettyPhoto.changePage=
function(c){currentGalleryPage=0;c=="previous"?(set_position--,set_position<0&&(set_position=a(pp_images).size()-1)):c=="next"?(set_position++,set_position>a(pp_images).size()-1&&(set_position=0)):set_position=c;rel_index=set_position;doresize||(doresize=!0);a(".pp_contract").removeClass("pp_contract").addClass("pp_expand");t(function(){a.prettyPhoto.open()})};a.prettyPhoto.changeGalleryPage=function(a){a=="next"?(currentGalleryPage++,currentGalleryPage>totalPage&&(currentGalleryPage=0)):a=="previous"?
(currentGalleryPage--,currentGalleryPage<0&&(currentGalleryPage=totalPage)):currentGalleryPage=a;slide_speed=a=="next"||a=="previous"?settings.animation_speed:0;slide_to=currentGalleryPage*itemsPerPage*itemWidth;$pp_gallery.find("ul").animate({left:-slide_to},slide_speed)};a.prettyPhoto.startSlideshow=function(){typeof l=="undefined"?($pp_pic_holder.find(".pp_play").unbind("click").removeClass("pp_play").addClass("pp_pause").click(function(){a.prettyPhoto.stopSlideshow();return!1}),l=setInterval(a.prettyPhoto.startSlideshow,
settings.slideshow)):a.prettyPhoto.changePage("next")};a.prettyPhoto.stopSlideshow=function(){$pp_pic_holder.find(".pp_pause").unbind("click").removeClass("pp_pause").addClass("pp_play").click(function(){a.prettyPhoto.startSlideshow();return!1});clearInterval(l);l=void 0};a.prettyPhoto.close=function(){$pp_overlay.is(":animated")||(a.prettyPhoto.stopSlideshow(),$pp_pic_holder.stop().find("object,embed").css("visibility","hidden"),a("div.pp_pic_holder,div.ppt,.pp_fade").fadeOut(settings.animation_speed,
function(){a(this).remove()}),$pp_overlay.fadeOut(settings.animation_speed,function(){a.browser.msie&&a.browser.version==6&&a("select").css("visibility","visible");settings.hideflash&&a("object,embed,iframe[src*=youtube],iframe[src*=vimeo]").css("visibility","visible");a(this).remove();a(window).unbind("scroll.prettyphoto");url=location.href;if(hashtag=url.indexOf("#!prettyPhoto")?!0:!1)location.hash="!prettyPhoto";settings.callback();doresize=!0;p=!1;delete settings}))};!pp_alreadyInitialized&&d()&&
(pp_alreadyInitialized=!0,hashRel=hashIndex=d(),hashIndex=hashIndex.substring(hashIndex.indexOf("/")+1,hashIndex.length-1),hashRel=hashRel.substring(0,hashRel.indexOf("/")),setTimeout(function(){a("a[rel^='"+hashRel+"']:eq("+hashIndex+")").trigger("click")},50));return this.unbind("click.prettyphoto").bind("click.prettyphoto",a.prettyPhoto.initialize)}})(jQuery);var pp_alreadyInitialized=!1;
function share_popup(a,d,e){window.open(a,"Popup","toolbar=no,locationbar=no,directories=no,titlebar=no,status=no,menubar=no,scrollbars=no,resizable=no,width="+d+",height="+e+",left="+(screen.width/2-d/2)+",top="+(screen.height/2-e/2))}var _timer=null,req=null,_is_locked=!1;function stopEventPropagation(a){if(a.stopPropagation)a.stopPropagation();else if(window.event)window.event.cancelBubble=!0}
function setBusy(a){var d=$("#ajaxbusy");a?(_is_locked=!0,d.css("position","fixed"),d.css("z-index","100"),d.css("top","0"),d.css("right","0px"),d.show()):(_is_locked=!1,d.hide())}var menu_active=!1;
jQuery(document).ready(function(){$("html > head").append("<style>#menu_nav li:hover ul { display: none; }</style>");jQuery("#menu_nav li").each(function(){if(jQuery(this).children("ul").length>0){var a;a=jQuery(this).attr("id");if(a==""||a==void 0)a=jQuery(this).attr("class").split(" ")[1];jQuery(this).children("a").after('<span id="'+a+'" class="m_downarrow">&nbsp;</span>')}});jQuery(".m_downarrow").click(function(){var a=jQuery(this).attr("id");jQuery("#"+a).children("ul:first").fadeIn();menu_active=
!0});jQuery("#menu_nav ul").hover(function(){},function(){jQuery(this).hide()});jQuery(".dropmenu").hover(function(){},function(){jQuery("#menu_nav ul").fadeOut();menu_active=!1});jQuery("#menu_nav li").hover(function(){menu_active&&jQuery(this).children("ul").show()},function(){menu_active&&jQuery(this).children("ul").hide()});$("a.bbc_img").prettyPhoto({social_tools:"",allow_resize:!0,animation_speed:"fast",show_title:!1});$("a.attach_thumb").prettyPhoto({social_tools:"",deeplinking:!1,overlay_gallery:!1,
animation_speed:"normal",opacity:1});$(".whoposted").click(function(){var a=$(this).attr("data-topic");a&&sendRequest(smf_scripturl,"action=xmlhttp;sa=whoposted;t="+a,$(this));return!1});$(".share_button").click(function(){share_popup($(this).attr("href"),700,400);return!1});$(".mcard").click(function(){var a=$(this).attr("data-id");a>0&&sendRequest(smf_scripturl,"action=xmlhttp;sa=mcard;u="+parseInt(a),$(this));return!1});$("#mcard_close").click(function(){$("#mcard").hide();$("#mcard_inner").html("");
$("#wrap").css("opacity","1.0");return!1});$(".givelike").click(function(){var a=parseInt($(this).attr("data-id"));if(a>0)switch($(this).attr("data-fn")){case "give":sendRequest(smf_scripturl,"action=xmlhttp;sa=givelike;m="+a,$(this));break;case "remove":sendRequest(smf_scripturl,"action=xmlhttp;sa=givelike;remove=1;m="+a,$(this));break;case "repair":sendRequest(smf_scripturl,"action=xmlhttp;sa=givelike;repair=1;m="+a,$(this))}return!1});$("table.table_grid td .input_check").change(function(){var a=
this;$(this).parent().parent().children("td").each(function(){$(a).is(":checked")?$(this).addClass("inline_highlight"):$(this).removeClass("inline_highlight")})});$("table.table_grid th .input_check").change(function(){$("table.table_grid td .input_check").each(function(){$(this).change()})});$("span.tpeek").hover(function(){$("#tpeekresult").remove();$(this).append('<a style="display:none;" href="#" onclick="firePreview('+$(this).attr("data-id")+', $(this)); return(false);" class="tpeek">Preview topic</a>');
$(this).children("a[class=tpeek]:first").fadeIn()},function(){});$("td.subject").hover(function(){},function(){$(this).find("a[class=tpeek]:first").remove()});$("a.collapse").click(function(){var a=$(this).children("img:first"),d=a.attr("src").match(/\/expand\./i)?1:0,e="action=xmlhttp;sa=collapse;c="+$(this).attr("data-id")+(d?";expand=1":";collapse=1");sendRequest(smf_scripturl,e,$(this));var e="#category_"+$(this).attr("data-id")+"_boards",f=a.attr("src");d?($(e).show(),d=f.replace("expand.gif",
"collapse.gif")):($(e).hide(),d=f.replace("collapse.gif","expand.gif"));a.attr("src",d);return!1});$("#sbtoggle").click(function(){$("#sidebar").css("display")=="none"?($("#container").animate({marginRight:sideBarWidth+15+"px"},50,function(){$("#sidebar").fadeIn()}),createCookie("smf_sidebar_disabled",0,300),$("#sbtoggle").html("&nbsp;&gt;")):($("#sidebar").fadeOut(200,function(){$("#container").animate({marginRight:"0"},50)}),createCookie("smf_sidebar_disabled",1,300),$("#sbtoggle").html("&nbsp;&lt;"))});
disableDynamicTime||$("abbr.timeago").timeago()});function submitTagForm(a){sendRequest(smf_scripturl,"action=xmlhttp;sa=tags;submittag=1;topic="+$("#tagtopic").val()+";tag="+encodeURIComponent($("#newtags").val()),a);$("#tagform").remove()}function firePreview(a,d){sendRequest(smf_scripturl,"action=xmlhttp;sa=mpeek;t="+a,d)}function timeOutError(){req&&(req.abort(),req=null);setBusy(0);alert("Error: Connection has timed out.")}
function setTimeOut(a){_timer=window.setTimeout(function(){timeOutError()},a)}function clearTimeOut(){_timer&&(clearTimeout(_timer),_timer=null)}function sendRequest(a,d,e){if(!_is_locked){var f=new XMLHttpRequest;if(f)typeof sSessionVar=="undefined"&&(sSessionVar="sesc"),a=a+"?"+(d+";"+sSessionVar+"="+sSessionId+";xml"),setTimeOut(1E4),req=f,f.onreadystatechange=function(){response(e)},f.open("GET",a,!0),setBusy(1),f.send(null)}}
function openResult(a,d){$("#wrap").css("opacity","0.6");$("#mcard").css("width",d+"px");$("#mcard_inner").html(req.responseText);var e=$("#mcard");e.css("position","fixed");e.css("top",($(window).height()-e.outerHeight())/2-200+"px");e.css("left",($(window).width()-e.outerWidth())/2+$(window).scrollLeft()+"px");e.show();e.css("z-index","100")}
function response(a){try{if(clearTimeOut(),req.readyState==4)if(req.status==200)if(setBusy(0),a.attr("class")=="whoposted")openResult(req.responseText,150);else if(a.attr("class")=="tpeek")openResult(req.responseText,710),$("div#mcard_inner abbr.timeago").timeago();else if(a.attr("class")=="givelike"){var d="#likers_msg_"+a.attr("data-id");$(d).html(req.responseText);a.attr("data-fn")=="give"?(a.attr("data-fn","remove"),a.html(smf_unlikelabel)):a.attr("data-fn")=="remove"&&(a.attr("data-fn","give"),
a.html(smf_likelabel))}else a.attr("id")=="addtag"?$("#addtag").before(req.responseText):a.attr("id")=="tags"?a.html(req.responseText):a.attr("class")!="collapse"&&(a.attr("id")=="sidebar"?(a.html(req.responseText),sidebar_content_loaded=!0):(openResult(req.responseText,500),$("div#mcard_inner abbr.timeago").timeago()));else req.status==500&&(setBusy(0),clearTimeOut())}catch(e){clearTimeOut(),setBusy(0)}}function openAdvSearch(){$("#adv_search").fadeIn()}
function submitSearchBox(){$("#search_form #i_topic").is(":checked")?$("#search_form #s_board").remove():($("#search_form #i_board").is(":checked")||$("#search_form #s_board").remove(),$("#search_form #s_topic").remove())}$("#adv_search").live("mouseleave",function(){$("#adv_search").hide()});