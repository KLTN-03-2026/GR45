const fs = require('fs');
const path = require('path');
const file = path.join(__dirname, 'src', 'views', 'driver', 'DashboardView.vue');
let code = fs.readFileSync(file, 'utf8');

code = code.replace(/const routeToStop \= async \(tram\) => \{[\s\S]*?console\.error\("Lỗi lấy chỉ đường từ OpenMap \(có thể do 403\), fallback sang OSRM:", error\);\n    \}\n  \}\n\};\n\nconst drawAllStops \= \(\) => \{/, `const routeToStop = async (tram) => {
  activeTargetStop.value = tram;
  // reset text
  tram.routingDistance = null;
  tram.routingDuration = null;

  const originCoord = \`\${currentPosition.value.lat},\${currentPosition.value.lng}\`;
  const destCoord = \`\${tram.toa_do_x},\${tram.toa_do_y}\`;

  try {
    const res = await openmapApi.direction(originCoord, destCoord);
    const data = res.data;
    let pathCoordinates = [];

    if (data.routes && data.routes.length > 0) {
      const route = data.routes[0];
      if (route.legs && route.legs.length > 0) {
        const leg = route.legs[0];
        if (leg.distance?.text) tram.routingDistance = leg.distance.text;
        if (leg.duration?.text) tram.routingDuration = leg.duration.text;
      }

      if (route.geometry && Array.isArray(route.geometry.coordinates)) {
        pathCoordinates = route.geometry.coordinates;
      } else if (route.overview_polyline && route.overview_polyline.points) {
        pathCoordinates = decodePolyline(route.overview_polyline.points);
      } else if (typeof route.geometry === "string") {
        pathCoordinates = decodePolyline(route.geometry);
      }
    } else if (data.paths && data.paths.length > 0) {
      const path = data.paths[0];
      if (path.distance) tram.routingDistance = (path.distance / 1000).toFixed(1) + " km";
      if (path.time) tram.routingDuration = Math.round(path.time / 1000 / 60) + " phút";

      if (path.points && path.points.coordinates) {
        pathCoordinates = path.points.coordinates;
      } else if (path.points && typeof path.points === "string") {
        pathCoordinates = decodePolyline(path.points);
      }
    }

    if (pathCoordinates.length > 0) {
      drawPath(pathCoordinates);
    } else {
      console.warn("Không tìm thấy đường đi từ API");
    }
  } catch (error) {
    console.error(
      "Lỗi lấy chỉ đường từ OpenMap (có thể do 403), fallback sang OSRM:",
      error,
    );
    try {
      const originLngLat = \`\${currentPosition.value.lng},\${currentPosition.value.lat}\`;
      const destLngLat = \`\${tram.toa_do_y},\${tram.toa_do_x}\`;
      const osrmRes = await openmapApi.getDrivingRoute(
        \`\${originLngLat};\${destLngLat}\`,
      );
      const osrmData = osrmRes.data;

      if (osrmData.code === "Ok" && osrmData.routes.length > 0) {
        const route = osrmData.routes[0];
        let pathCoordinates = route.geometry.coordinates;
        if (route.distance) tram.routingDistance = (route.distance / 1000).toFixed(1) + " km";
        if (route.duration) tram.routingDuration = Math.round(route.duration / 60) + " phút";

        if (pathCoordinates.length > 0) {
          drawPath(pathCoordinates);
        }
      }
    } catch (osrmError) {
      console.error("Lỗi fallback OSRM:", osrmError);
    }
  }
};

const drawPath = (pathCoordinates) => {
  plannedRouteGeoJSON = {
    type: "Feature",
    geometry: {
      type: "LineString",
      coordinates: pathCoordinates,
    },
  };

  if (mapInstance.getSource("plannedRoute")) {
    mapInstance.getSource("plannedRoute").setData(plannedRouteGeoJSON);
  } else {
    mapInstance.addSource("plannedRoute", {
      type: "geojson",
      data: plannedRouteGeoJSON,
    });
    mapInstance.addLayer({
      id: "plannedRoute",
      type: "line",
      source: "plannedRoute",
      layout: {
        "line-join": "round",
        "line-cap": "round",
      },
      paint: {
        "line-color": "#3b82f6",
        "line-width": 4,
        "line-dasharray": [2, 2],
      },
    });
  }

  const bounds = new maplibregl.LngLatBounds(
    pathCoordinates[0],
    pathCoordinates[0],
  );
  for (const coord of pathCoordinates) {
    bounds.extend(coord);
  }
  mapInstance.fitBounds(bounds, { padding: 50 });
};

const drawAllStops = () => {`);

fs.writeFileSync(file, code);
