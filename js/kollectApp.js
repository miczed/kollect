


var app = angular.module("kollectApp", ['ngRoute','kollectControllers','ui.bootstrap','ngCookies']);

app.config(['$routeProvider',
  function($routeProvider) {
    $routeProvider.
      when('/', {
        templateUrl: 'tpl/main.html',
        controller: 'mainCtrl'
      }).
      when('/:playlistKey', {
        templateUrl: 'tpl/main.html',
        controller: 'mainCtrl'
      }).
      otherwise({
        redirectTo: '/'
      });
  }]);