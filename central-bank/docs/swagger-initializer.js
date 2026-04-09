window.onload = function() {
  var docsPath = window.location.pathname;
  var docsIndex = docsPath.lastIndexOf("/docs");
  var basePath = docsIndex >= 0 ? docsPath.slice(0, docsIndex) : "";
  var cacheBustedSpecUrl = basePath + "/openapi/central-bank.yaml?v=" + Date.now();

  window.ui = SwaggerUIBundle({
    url: cacheBustedSpecUrl,
    dom_id: "#swagger-ui",
    deepLinking: true,
    presets: [
      SwaggerUIBundle.presets.apis
    ],
    layout: "BaseLayout",
    validatorUrl: null
  });
};
