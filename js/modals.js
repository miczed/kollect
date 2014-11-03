
kollectControllers.controller('ModalLoginCtrl', function ($scope, $modalInstance,$http) {
  $scope.user = {};
  $scope.error = false;
  $scope.ok = function () {
    $http.post('api/login', $scope.user).success(function(data){
      if(data.error) {
	      $scope.error = data.error.text;
      } else if(data.id) {
	      $scope.user = data;
	      $modalInstance.close($scope.user);
	      console.log($scope.user);
      }
      
    });
  };

  $scope.cancel = function () {
    $modalInstance.dismiss('cancel');
  };
});