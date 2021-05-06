// 范围圆图 https://developer.amap.com/demo/javascript-api/example/marker/labelsmarker

var repeaters = [];
var currentZoom = 11;
var hidePointLabelWhenCountGreaterThan = /iPhone|Android/i.test(navigator.userAgent) ? 10 : 40;
var currentCenter = {
  lat: null,
  lng: null,
};
// mapOptions: https://developer.amap.com/api/javascript-api/reference/map
var map = new AMap.Map("container", {
  mapStyle: 'normal',
  viewMode: '2D',
  // mapStyle: "amap://styles/58f06ea8b31a8391156e2c4c6b4143e0", // normal dark
  // viewMode: "3D",
  resizeEnable: true,
  pitch: 50,
  // rotation: 35,
  zooms: [7, 13],
  zoom: currentZoom, // 国5 省7 市11 街17
  // >=3显示省级，>=6显示各市, >=8显示县
});

// 地图上的工具
map.plugin(["AMap.MapType", "AMap.ToolBar", "AMap.Scale"], function () {
  map.addControl(new AMap.MapType({}));
  map.addControl(
    new AMap.ToolBar({
      ruler: false,
      locate: true,
      direction: false,
      autoPosition: false,
    })
  );
  map.addControl(new AMap.Scale());
});

var infoWindow = new AMap.InfoWindow({ offset: new AMap.Pixel(0, -30) });

// https://developer.amap.com/api/javascript-api/reference/layer/#AMap.LabelsLayer
// demo: https://lbs.amap.com/api/javascript-api/example/marker/labelsmarker
var labelLayer = new AMap.LabelsLayer({
  zooms: [3, 20],
  zIndex: 1000,
  collision: false, // 开启标注避让(不显示被影响到的项)，默认为开启，v1.4.15 新增属性
  animation: true, // 开启标注淡入动画，默认为开启，v1.4.15 新增属性
});
map.add(labelLayer);

var labels = [];

function removeRepeater() {
  try {
    // object3Dlayer.remove(lines3D);
    // object3Dlayer.remove(points3D);
    map.remove(labels);
    labels = [];
  } catch (e) {
    console.log("Repeaters remove faile", e);
  }
}
function getRepeater(from = "") {
  removeRepeater();
  var zoom = map.getZoom();
  var bounds = map.getBounds();
  var west = null;
  var east = null;
  var south = null;
  var north = null;
  // var height = -parseInt(map.getScale() / 3);
  if (bounds.path) {
    for (var i = 0; i < bounds.path.length; i++) {
      var path = bounds.path[i];
      west = west ? Math.min(west, path[0]) : path[0];
      east = east ? Math.max(east, path[0]) : path[0];
      north = north ? Math.max(north, path[1]) : path[1];
      south = south ? Math.min(south, path[1]) : path[1];
    }
  } else {
    north = bounds.northeast.lat;
    east = bounds.northeast.lng;
    south = bounds.southwest.lat;
    west = bounds.southwest.lng;
  }
  var params = {
    zoom: zoom,
    west: west,
    east: east,
    south: south,
    north: north,
  };
  if (db) {
    var sql = `
          SELECT
            r.name,
            r.configuration,
            r.via,
            l.name AS location,
            l.longitude,
            l.latitude
          FROM repeater AS r
          LEFT JOIN location AS l ON r.location_id = l.id
          WHERE r.location_id IN (
            SELECT
              id
            FROM location
            WHERE 1
              AND longitude >= '${params.west}'
              AND longitude <= '${params.east}'
              AND latitude <= '${params.north}'
              AND latitude >= '${params.south}'
          )`;
    var contents = db.exec(sql);
    repeaters =
      contents[0].values.map((v) => {
        var item = {};
        contents[0].columns.forEach((key, i) => {
          item[key] = v[i];
        });
        return item;
      }) || [];
    // 按坐标分组（同一个坐标上可能有多条记录）
    repeaters = (function (items = []) {
      var array = [];
      // 分组
      var groupd = {};
      for (let i = 0; i < items.length; i++) {
        var v = items[i];
        var key = v.longitude + "," + v.latitude;
        if (!groupd[key]) {
          groupd[key] = [];
        }
        groupd[key].push(v);
      }
      // 将分组变回数组
      for (var key in groupd) {
        if (Object.prototype.hasOwnProperty.call(groupd, key)) {
          var v = groupd[key];
          var r = (v.length > 0 && v[0]) || null;
          if (r) {
            var configurations = [];
            for (var i = 0; i < v.length; i++) {
              var e = v[i];
              configurations.push(e.name + ": " + e.configuration);
            }
            r.total = configurations.length || 1;
            r.configuration = configurations.join(";\n");
            var type = "";
            var isDigit = r.configuration.indexOf("数") != -1;
            var isAnalog = r.configuration.indexOf("模") != -1;
            if (isDigit && !isAnalog) {
              type = "digit";
            } else if (isAnalog && !isDigit) {
              type = "analog";
            } else {
              type = "other";
            }
            r.type = type;
            array.push(r);
          }
        }
      }
      return array;
    })(repeaters);
    // 在地图上标点
    for (var p = 0; p < repeaters.length; p++) {
      var v = repeaters[p];
      var marker = new AMap.Marker({
        title: v.configuration,
        position: [v.longitude, v.latitude],
        icon: getIcon(v),
        topWhenClick: true,
        map: map,
        offset: new AMap.Pixel(-12, -30),
      });
      marker.content =
        '<div class="point-tip ' +
        v.type +
        '"><div class="content"><div>' +
        v.configuration.replace(/[\r\n]/g, "</div><div>") +
        "</div></div></div>";

      // 密度：低有label，高无label
      if (repeaters.length < hidePointLabelWhenCountGreaterThan) {
        marker.setLabel({
          content: marker.content,
          direction: "top", // 可选值：'top'|'right'|'bottom'|'left'|'center'，默认值：'top'
          offset: new AMap.Pixel(-0, -0),
        });
      } else {
        //marker.setLabel({
        //  content: '<div class="point-tip ' + v.type + '"><div class="content">' + (v.location || v.name) + '</div></div>',
        //  direction: 'top', // 可选值：'top'|'right'|'bottom'|'left'|'center'，默认值：'top'
        //  offset: new AMap.Pixel(-0, -0),
        //});
        marker.on("click", function (e) {
          infoWindow.setContent(e.target.content);
          infoWindow.open(map, e.target.getPosition());
        });
      }
      labels.push(marker);
    }
  } else {
    console.warn("db not ready.");
  }
}
function getIcon(repeater) {
  var total = repeater.total || 1;
  total = total > 10 ? 10 : total;
  var offset = {
    x: 0,
    y: 0,
  };
  switch (repeater.type) {
    case "digit":
      offset = {
        x: -8,
        y: -135,
      };
      break;
    case "analog":
      offset = {
        x: -8,
        y: -47,
      };
      break;
    default:
      offset = {
        x: -8,
        y: -223,
      };
  }
  offset.x = offset.x - 44 * (total - 1);
  var icon = new AMap.Icon({
    size: new AMap.Size(25, 34),
    image: "//a.amap.com/jsapi_demos/static/images/poi-marker.png",
    imageSize: new AMap.Size(437, 262),
    imageOffset: new AMap.Pixel(offset.x, offset.y),
  });
  return icon;
}
// 地图中心移动时更新标点
map.on("moveend", function () {
  var center = map.getCenter();
  if (currentCenter.lat == center.lat && currentCenter.lng == center.lng) {
    return;
  }
  currentCenter = center;
  getRepeater("onMoveend");
});
// 地图中心移动时更新标点
map.on("zoomend", function () {
  var zoom = map.getZoom();
  if (zoom == currentZoom) {
    return;
  }
  currentZoom = zoom;
  // console.log('zoomend:', zoom);
  getRepeater("onZoomend");
});
// 获取数据
map.on("complete", function () {
  getRepeater("onComplete");
});
