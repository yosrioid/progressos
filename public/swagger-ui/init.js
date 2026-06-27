window.onload = function () {
  var spec = JSON.parse(document.getElementById('api-spec').textContent);
  SwaggerUIBundle({
    spec: spec,
    dom_id: '#swagger-ui',
    presets: [SwaggerUIBundle.presets.apis],
    layout: 'BaseLayout',
    deepLinking: true,
    tryItOutEnabled: true,
    persistAuthorization: true,
  });
};
