function addMarker(target_map, map_marker_positions) {
	positions = makeLocationArray(map_marker_positions);
	var oSize = new nhn.api.map.Size(28, 37);
	var oOffset = new nhn.api.map.Size(14, 37);
	var oIcon = new nhn.api.map.Icon('http://static.naver.com/maps2/icons/pin_spot2.png', oSize, oOffset);

	// 전체 마커 생성
	for(var i = 0; i < positions.length; i++)
	{
		var markers = new nhn.api.map.Marker(oIcon, {
			point: positions[i]
		});
		target_map.addOverlay(markers);
	}

}
function makeLocationArray(str_position) {
	var arr_positons = new Array();
	var positions = str_position.split(";");
	for(var i = 0; i < positions.length; i++)
	{
		if(!positions[i].trim()) continue;
		var position = positions[i].split(",");
		arr_positons[i] = new nhn.api.map.LatLng(position[0],position[1]);
	}
	return arr_positons;
}