/* Map Components by Misol. (c) 2014 MinSoo Kim. (misol.kr@gmail.com) */
var map_zoom = 13, map_lat = '', map_lng = '', marker_latlng = '', map = '', marker = '', saved_location = new Array(), result_array = new Array(), infowindow = '', result_from = '';

function soo_save_location(i,j) { //위치 정보를 배열로 저장
	i = parseInt(i,10);
	if(j!=1) {
		saved_location[i] = new Array();
		saved_location[i][0] = map.getZoom();
		saved_location[i][1] = map.getCenter().lat();
		saved_location[i][2] = map.getCenter().lng();
		saved_location[i][3] = marker_latlng.lat();
		saved_location[i][4] = marker_latlng.lng();
	}
	var html = '<form action="#" onsubmit="soo_save_location(this.locations.value); return false" id="form_to_save"><select size="1" id="locations">', n=0;
	for(n=0;n<saved_location.length;n++) {
		if(n==i) {
			html += "<option value=\""+n+"\" selected=\"selected\">"+(n+1)+'['+soo_editing+']'+"</option>";
		} else {
			html += "<option value=\""+n+"\">"+(n+1)+"</option>";
		}
	}
	html += "<option value=\""+n+"\">"+(n+1)+"</option>";
	html += '</select><br /><span class="button red"><input id="save_btn" type="submit" value="'+soo_save+'" /></span> <span class="button"><button type="button" onclick="soo_load_location();">'+soo_edit+'</button></span></form>';
	jQuery("#save_form").html(html);
}
function soo_load_location() { //위치 정보를 배열에서 로드
	var form = document.getElementById('form_to_save')
	var i = form.locations.selectedIndex;
	i = parseInt(i,10);
	if(!saved_location[i]) {alert(soo_nulledit); return;}

	jQuery("#map_zoom").val(saved_location[i][0]);
	jQuery("#lat").val(saved_location[i][1]);
	jQuery("#lng").val(saved_location[i][2]);

	map_zoom = parseInt(jQuery("#map_zoom").val(),10);
	center = new google.maps.LatLng(saved_location[i][1], saved_location[i][2]);

	map.setCenter(center);
	map.setZoom(map_zoom);
	marker.setMap(null);
	marker_latlng = new google.maps.LatLng(saved_location[i][3], saved_location[i][4]);
	marker = new google.maps.Marker({
		position: marker_latlng, 
		map: map, 
		draggable: true
	});
	soo_marker_event();
	marker.setMap(map);
	infowindow.close();
	infowindow = new google.maps.InfoWindow({
		content: dragmarkertext + "<br /><strong>" + saved_location[i][5] + "</strong>",
		disableAutoPan: true
	});
	infowindow.open(map,marker);
	soo_save_location(i,1);
}
function map_point(i) { //검색된 위치 정보를 배열에서 로드
	map_zoom = parseInt(jQuery("#map_zoom").val(),10);
	center = result_array[i].geometry.location;
	//viewport 가 정확히 오면 유용한데, 정확한 값을 반환하지 않는다.
	//bounds = result_array[i].geometry.viewport;
	//map.fitBounds(bounds);
	map.setCenter(center);
	latlng = center;
	marker.setMap(null);
	marker_latlng = result_array[i].geometry.location;
	marker = new google.maps.Marker({
		position: marker_latlng, 
		map: map, 
		draggable: true
	});
	soo_marker_event();
	marker.setMap(map);
	infowindow.close();
	infowindow = new google.maps.InfoWindow({
		content: dragmarkertext + "<br /><strong>" + result_array[i].formatted_address + "</strong>",
		disableAutoPan: true
	});
	infowindow.open(map,marker);
}
function view_list() { //검색된 위치 정보를 배열에서 리스트로 뿌림
	var html = '';
	if(result_array.length == 0) 
	{
		alert(no_result);
		return;
	}
	for(var i=0;i<result_array.length;i++) {
		if(i==0) {
			html += '<ul id="view_list">';
		}
		if(result_array.length==1) { map_point('0'); }
		html += "<li class=\"result_lists\"><a href=\"javascript:map_point('"+i+"');\">"+result_array[i].formatted_address +"</a></li>";
	}
	html += '</ul>';
	jQuery("#result_list_layer").html(html);
	window.location.href = '#view_list';
}
function addAddressToMap(response, status) {
	if(status==200) { result_from = 'naver'; }
	else if(status == google.maps.GeocoderStatus.OK) { result_from = 'google'; }

	if (status != google.maps.GeocoderStatus.OK && status != 200) {
		alert(no_result + "\nGoogle Error Code : "+status);
	} else {
		result_array = new Array();
		result_array = response;
		view_list();
	}
}
function showLocation(address) {
	result_from = '';
	if(!address) return;

	var params = new Array();
	params['component'] = "map_components";
	params['address'] = address;
	params['method'] = "search";

	var response_tags = new Array('error','message','results');
	exec_xml('editor', 'procEditorCall', params, function(a,b) { complete_search(a,b,address); }, response_tags);
}
function complete_search(ret_obj, response_tags, address) {
	var results = ret_obj['results'];
	if(results) results = results.item;
	else results = new Array();

	address_adder(results);
	//geocoder.geocode({'address': address}, function(a,b) {address_adder(a, b,address,results); });
}
function address_adder(results) {
	var response = new Array();
	if(typeof(results.length) == "undefined") results = new Array(results);

	for(var i=0;i<results.length;i++) {
		if(results[i].formatted_address || results[i].formatted_address != null) {
			response[i] = { from: results[i].result_from,
				formatted_address: results[i].formatted_address,
				geometry: {location : new google.maps.LatLng(results[i].geometry.lat, results[i].geometry.lng) } };
		}
	}
	addAddressToMap(response, 200);
}
function soo_marker_event() {
	google.maps.event.addListener(marker, "dragstart", function() {
		infowindow.close();
	});
	google.maps.event.addListener(marker, "dragend", function(event) {
	
	if(event.latLng) {
			geocoder.geocode({'latLng': event.latLng}, function(rst, stat) {
				if (stat == google.maps.GeocoderStatus.OK) {
					if (rst[1]) {
						infowindow.close();
						infowindow = new google.maps.InfoWindow({ content: dragmarkertext+"<br />"+rst[1].formatted_address });
						infowindow.open(map,marker);
					}
					else {
						infowindow.close();
						infowindow = new google.maps.InfoWindow({ content: dragmarkertext });
						infowindow.open(map,marker);
					}
				}
			});
			marker_latlng = event.latLng;

		}
	});
}
function getGoogleMap() {
	var mapOption = {
		zoom: 8,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}
	map = new google.maps.Map(document.getElementById("map_canvas"), mapOption);

	if(typeof(opener)=="undefined") return;

	var node = opener.editorPrevNode;
	infowindow = new google.maps.InfoWindow();

	if(node && node.nodeName == "IMG") {
		jQuery("#width").val(jQuery(node).width());
		jQuery("#height").val(jQuery(node).height());
		if(!node.getAttribute("location_no")) {
			map_lat = node.getAttribute("map_lat");
			map_lng = node.getAttribute("map_lng");
			marker_lat = node.getAttribute("marker_lat");
			marker_lng = node.getAttribute("marker_lng");
			marker_latlng = new google.maps.LatLng(marker_lat, marker_lng);
			latlng = marker_latlng;
			map_zoom = parseInt(node.getAttribute("map_zoom"),10);
			if(marker_latlng) {
				latlng = marker_latlng
			}
			if(map_zoom) {
				jQuery("#map_zoom").val(map_zoom);
			}
			if(map_lat) {
				jQuery("#lat").val(map_lat);
			}
			if(map_lng) {
				jQuery("#lng").val(map_lng);
			}
		} else {
			var location_no = parseInt(node.getAttribute("location_no"),10), html = '<form action="#" onsubmit="soo_save_location(this.locations.value); return false" id="form_to_save"><select size="1" id="locations">', i=0;
			for(i=0;i<location_no;i++) {
				saved_location[i] = new Array();
				saved_location[i][0] = node.getAttribute("map_zoom"+i);
				saved_location[i][1] = node.getAttribute("map_lat"+i);
				saved_location[i][2] = node.getAttribute("map_lng"+i);
				saved_location[i][3] = node.getAttribute("marker_lat"+i);
				saved_location[i][4] = node.getAttribute("marker_lng"+i);
				if(i==0) {
					html += "<option value=\""+i+"\" selected=\"selected\">"+(i+1)+'['+soo_editing+']'+"</option>";
				} else {
					html += "<option value=\""+i+"\">"+(i+1)+"</option>";
				}
			}
			html += "<option value=\""+i+"\">"+(i+1)+"</option>";
			html += '</select><br /><span class="button red"><input id="save_btn" type="submit" value="'+soo_save+'" /></span> <span class="button"><button type="button" onclick="soo_load_location();">'+soo_edit+'</button></span></form>';
			jQuery("#save_form").html(html);

			map_lat = saved_location[0][1];
			map_lng = saved_location[0][2];
			marker_lat = saved_location[0][3];
			marker_lng = saved_location[0][4];
			marker_latlng = new google.maps.LatLng(marker_lat, marker_lng);
			latlng = marker_latlng;
			map_zoom = parseInt(saved_location[0][0],10);
			if(marker_latlng) {
				latlng = marker_latlng;
			}
			if(map_zoom) {
				jQuery("#map_zoom").val(map_zoom);
			}
			if(map_lat) {
				jQuery("#lat").val(map_lat);
			}
			if(map_lng) {
				jQuery("#lng").val(map_lng);
			}
		}
		map.setCenter(new google.maps.LatLng(map_lat, map_lng));
		map.setZoom(map_zoom);
		var center = map.getCenter();
	} else {
		jQuery("#lat").val(defaultlat);
		map_lat = defaultlat;
		jQuery("#lng").val(defaultlng);
		map_lng = defaultlng;
		map.setCenter(new google.maps.LatLng(map_lat, map_lng));
		var center = map.getCenter();
		marker_latlng = center;
		jQuery("#width").val('600');
		jQuery("#height").val('400');
		latlng = center;
	}
	soo_map_set();
	map.setZoom(map_zoom);

	google.maps.event.addListener(map, 'dragend', function() {
		center = map.getCenter();
		jQuery("#lng").val(center.lng());
		jQuery("#lat").val(center.lat());
		jQuery("#map_zoom").val(map.getZoom());
		var bounds = map.getBounds();
		var southWest = bounds.getSouthWest();
		var northEast = bounds.getNorthEast();
		if((latlng.lng()<southWest.lng() || northEast.lng()<latlng.lng()) || (latlng.lat()<southWest.lat() || northEast.lat()<latlng.lat())) {
			marker.setMap(null);
			infowindow.close();
			latlng = center;
			marker_latlng = latlng;
			marker = new google.maps.Marker({
				position: center, 
				map: map,
				draggable: true
			});
			marker.setMap(map);
			infowindow = new google.maps.InfoWindow({
				content: dragmarkertext,
				disableAutoPan: true
			});
			infowindow.open(map,marker);
			soo_marker_event();
		}
	});

	marker = new google.maps.Marker({
			position: latlng, 
			map: map, 
			draggable: true
		});
	soo_marker_event();
	marker.setMap(map);
	geocoder = new google.maps.Geocoder();
	jQuery("#lng").val(center.lng());
	jQuery("#lat").val(center.lat());
	jQuery("#map_zoom").value = map.getZoom();
	marker_latlng = latlng;
	infowindow.close();
	windowResize();
}
function windowResize() {
	var contentWidth = document.getElementById("#bodyDiv").offsetWidth;
	var contentHeight = document.getElementById("#bodyDiv").offsetHeight;
	window.resizeTo(contentWidth,contentHeight);
}
function insertMap(obj) {
	if(typeof(opener)=="undefined") return;
	var width = jQuery("#width").val(), height = jQuery("#height").val();
	if(saved_location.length == 0 || saved_location.length == 1) {
		map_zoom = map.getZoom();
		map_lat = map.getCenter().lat();;
		map_lng = map.getCenter().lng();;
		if(!width) {width = '600'}
		if(!height) {height = '400'}
		if(!map_zoom) {map_zoom = '13'}

		if(insert_lat || insert_lng) {
			if(insert_lat) {
				if(typeof(opener.document.getElementsByName(insert_lat)[0]) != "undefined") {
					var val = opener.document.getElementsByName(insert_lat)[0].value;
					if(typeof(val)=="string") opener.document.getElementsByName(insert_lat)[0].value = map_lat;
				}
			}
			if(insert_lng) {
				if(typeof(opener.document.getElementsByName(insert_lng)[0]) != "undefined") {
					var val = opener.document.getElementsByName(insert_lng)[0].value;
					if(typeof(val)=="string") opener.document.getElementsByName(insert_lng)[0].value = map_lng;
				}
			}
		}
		var text = "<img src=\"https://maps-api-ssl.google.com/maps/api/staticmap?center="+map_lat+','+map_lng+"&zoom="+map_zoom+"&size="+width+"x"+height+"&markers=size:mid|"+marker_latlng.lat()+','+marker_latlng.lng()+"&sensor=false\" editor_component=\"map_components\" ment=\""+ment+"\" map_lat=\""+map_lat+"\" map_lng=\""+map_lng+"\" marker_lng=\""+marker_latlng.lng()+"\" marker_lat=\""+marker_latlng.lat()+"\" map_zoom=\""+map_zoom+"\" width=\""+width+"\" height=\""+height+"\" style=\"width:"+width+"px;height:"+height+"px;border:2px dotted #FF0033;background:url('https://maps-api-ssl.google.com/maps/api/staticmap?language="+soo_langcode+"&amp;center="+map_lat+","+map_lng+"&amp;zoom="+map_zoom+"&amp;size="+width+"x"+height+"&amp;markers=size:mid|"+marker_latlng.lat()+","+marker_latlng.lng()+"&amp;sensor=false') no-repeat center;\" />";
	} else {
		var text = "<img src=\"https://maps-api-ssl.google.com/maps/api/staticmap?center="+saved_location[0][1]+','+saved_location[0][2]+"&zoom="+saved_location[0][0]+"&size="+width+"x"+height+"&sensor=false\" editor_component=\"map_components\" width=\""+width+"\" height=\""+height+"\" style=\"width:"+width+"px;height:"+height+"px;border:2px dotted #FF0033;\"";
		text += ' location_no="' + saved_location.length + '"';
		for(var i=0;i<saved_location.length;i++) {
			text += ' map_zoom' + i + '="' + saved_location[i][0] + '"';
			text += ' map_lat' + i + '="' + saved_location[i][1] + '"';
			text += ' map_lng' + i + '="' + saved_location[i][2] + '"';
			text += ' marker_lat' + i + '="' + saved_location[i][3] + '"';
			text += ' marker_lng' + i + '="' + saved_location[i][4] + '"';
			saved_location[i][5] = saved_location[i][5].replace(/</g,'[[STS[['); //태그 구분자 치환
			saved_location[i][5] = saved_location[i][5].replace(/>/g,']]STS]]'); //태그 구분자 치환
			saved_location[i][5] = saved_location[i][5].replace(/=/g,'[[STS_EQ]]');
			text += ' ment' + i + '="' + saved_location[i][5] + '"';
		}
		text += " />";
	}
	opener.editorFocus(opener.editorPrevSrl);
	var iframe_obj = opener.editorGetIFrame(opener.editorPrevSrl)
	opener.editorReplaceHTML(iframe_obj, text);
	opener.editorFocus(opener.editorPrevSrl);
	window.close();
}
jQuery(document).ready(function() { getGoogleMap(); });