var kollectControllers = angular.module('kollectControllers', ['ui.bootstrap','ngCookies']);

// permalink to a track
var track_url = 'https://soundcloud.com/bergsofficial/avicii-the-days-jasmine-thompson-cover-bergs-remix-1';

function millisToMinutesAndSeconds(millis) {
  var minutes = Math.floor(millis / 60000);
  var seconds = ((millis % 60000) / 1000).toFixed(0);
  return minutes + ":" + (seconds < 10 ? '0' : '') + seconds;
}



app.filter('timeago', function(){
  return function(date){
    return moment(date).fromNow();
  };
});
 
app.filter('unix', function(){
  return function(date){
    return millisToMinutesAndSeconds(date);
  };
}); 
 
app.directive('timeago', function() {
  return {
    restrict:'A',
    link: function(scope, element, attrs){
      attrs.$observe("timeago", function(){
        element.text(moment(attrs.timeago).fromNow());
      });
    }
  };
});



// Array Remove - By John Resig (MIT Licensed)
Array.prototype.remove = function(from, to) {
  var rest = this.slice((to || from) + 1 || this.length);
  this.length = from < 0 ? this.length + from : from;
  return this.push.apply(this, rest);
};


// stream track id 293
  
kollectControllers.controller("mainCtrl", function($scope,$interval,$filter,$http,$routeParams, smoothScroll,$modal,$cookies) {
    
    $scope.soundcloud = true; //Debug Variable, falls Soundcloud Down ist
    $scope.sidebar = false; //Sidebar aktiv oder nicht
    $scope.user_menu = false;
    $scope.random = false; //Shuffle Modus aktiv oder nicht
    $scope.repeat = false; //Repeat Modus aktiv oder nicht
    $scope.playing = false; //Abspielen oder nicht
    $scope.loadedId = 0; //Aktuell geladener Song
    $scope.orderId = 0; //Aktuell geladener Song nach Reihenfolge
    $scope.timelineStyle = {}; //Stil für Timeline Verschiebung
    $scope.positionStyle = {}; //Stil für Position Verschiebung
    $scope.timelinescroll = 0; //Offset für Timeline verschiebung
    $scope.track = {}; //Aktuell geladener Track
    $scope.sound = ''; //Aktuelll geladener Song
    $scope.position = 0; //Position des aktuellen Songs in Prozent
    $scope.time = 0; //Position des aktuellen Songs in Millisekunden
	$scope.user = false //Aktuell eingeloggter Benutzer
	$scope.newSong = {}; 
	$scope.playlist = {};
	$scope.collectors = {};
	$scope.collections = {};
	$scope.order = Array();
	$scope.alerts = Array();
	if(!$routeParams.playlistKey) {
		$routeParams.playlistKey = "test";
	}
	if($scope.soundcloud) {
		SC.initialize({
			client_id: 'ddbbf3a736811e6b79a53add7940155b'
		});
	}
	$scope.ticket = $cookies.ticket;
    if($scope.ticket) {
	    $http.get('api/cookie/' + $scope.ticket).success(function(data) {
	    	$scope.user = data;
	    	$scope.loadCollections();
	   })
   }
    
	$http.get('api/playlists/' + $routeParams.playlistKey).success(function(data) {
    	$scope.playlist.title = data.title;
    	$scope.collectors = data.collectors;
    	$scope.playlist.key = data.unique_key;
    	$scope.originalSongs = data.songs;
		$scope.songs = data.songs;
		//$scope.order = $scope.songs;
		if($scope.songs.length > 0) {
			$scope.orderNormal(); 
			$scope.play($scope.orderId);
		}
   }).
  error(function(data, status, headers, config) {
    // called asynchronously if an error occurs
    // or server returns response with an error status.
  });	
	
    
    
    $scope.shuffle = function(o){ //v1.0
    	for(var j, x, i = o.length; i; j = Math.floor(Math.random() * i), x = o[--i], o[i] = o[j], o[j] = x);
		return o;
	};
    $scope.orderNormal = function() {
	    if($scope.order.length > 0) {
		    $scope.orderId = $scope.order[$scope.orderId];
	    }
	   	$scope.order = [];
	    var index;
		for (index = 0; index < $scope.songs.length; ++index) {
		    $scope.order.push(index);
		}
    }
    $scope.orderShuffle = function() {
	    $scope.order.remove($scope.orderId);
	    $scope.shuffle($scope.order);
	    $scope.order.unshift($scope.orderId);
	    console.log($scope.order);
		$scope.orderId = 0;
    }
    
    
    $scope.toggleSidebar = function() {
          $scope.sidebar = $scope.sidebar === false ? true: false;
          $scope.user_menu = false;
    };
     $scope.toggleUserMenu = function() {
          $scope.user_menu = $scope.user_menu === false ? true: false;
          $scope.sidebar = false;
    };
    $scope.toggleRepeat = function() {
          $scope.repeat = $scope.repeat === false ? true: false;
    };
    $scope.toggleRandom = function() {
          
          $scope.random = $scope.random === false ? true: false;
		  
		  if($scope.random) {
			  $scope.orderShuffle();
		  } else {
			  $scope.orderNormal();
		  }
		  
    };
     $scope.togglePlaying = function() {
          $scope.playing = $scope.playing === false ? true: false;
          if($scope.sound != '') {
	          if($scope.playing) {
		          $scope.playSC();
	          } else {
		          $scope.sound.pause();
	          }
          }
          
    };
    $scope.play = function(value,play_song) {
    	if(play_song) {
	    	$scope.playing = true;
    	}
    	console.log("LOAD SONG WIHT ID: " + value);
    	console.log($scope.songs);
    	$scope.songs[$scope.loadedId].playing = false;
    	$scope.loadedId = value;
    	console.log("Playing Song #" + value);
	    $scope.songs[value].playing = true;
	    $scope.track = $scope.songs[value];
	    $scope.timelinescroll = value * 140;
	    smoothScroll.scrollTo($scope.timelinescroll);
	    $scope.loadSC($scope.songs[value]['sc_id']);
    }
    $scope.prevSong = function() {
    	
	    $scope.songs[$scope.order[$scope.orderId]].playing = false;
	    if($scope.orderId - 1 < 0) {
		    $scope.orderId = $scope.songs.length - 1;
	    } else {
		    $scope.orderId = $scope.orderId - 1;
	    }
	    $scope.play($scope.order[$scope.orderId]);
	    console.log('Order ID: ' + $scope.orderId + " Song ID: " + $scope.order[$scope.orderId]);
    }
    $scope.nextSong = function() {
	    $scope.songs[$scope.order[$scope.orderId]].playing = false;
	    if($scope.orderId + 1 >= $scope.songs.length) {
		    $scope.orderId = 0;
	    } else {
		    $scope.orderId = $scope.orderId + 1;
	    }
	    $scope.play($scope.order[$scope.orderId]);
    }

    timeoutId = $interval(function() {
        $scope.positionStyle = { 'width': $scope.position + '%'};
		$scope.outputTime = $filter('date')($scope.time,'m:ss');
    }, 300);
	$scope.playSCbyURL = function(url) {
		if($scope.soundcloud) {
			var track = SC.get('/resolve', { url: url }, function(track) {
				$scope.playSC(track.id);
				return track;
			});
		}
		//$scope.playSC(track.id);
		
		return track;
	}
	$scope.getSCInfo = function(id) {
		if($scope.soundcloud) {
			var track = SC.get('/tracks/' + id, { }, function(track) {
				var cover = track.artwork_url;
				return track;
			});
		}
		return track;
	}

	$scope.loadSC = function(id) {
		if($scope.sound != '') {
			$scope.sound.destruct();
		}
		if($scope.soundcloud) {
			SC.stream("/tracks/" + id, function(sound){
				$scope.sound = sound;
				if($scope.playing) {
					$scope.playSC();
	
				}	
			});
		}
	}
	$scope.whileplaying = function() {
		var total = 100 / (this.durationEstimate / this.position);
		$scope.position = total;	
		$scope.time = this.position;		
	}
	$scope.playSC = function() {
		$scope.sound.play({ 
			whileplaying: $scope.whileplaying,
			onfinish: $scope.nextSong
		});
	}
	$scope.getSCInfo(172419376);
	
	$scope.test = function (message) {
		console.log('TEST: ' + message);
		
	}
	$scope.openModalLogin = function() {
	    var modalInstance = $modal.open({
	      templateUrl: 'tpl/modalLogin.html',
	      controller: 'ModalLoginCtrl',
	      size: 'sm',
	    });
	
	    modalInstance.result.then(function (user) {
	      $scope.user = user;

	      $scope.loadCollections();

	    }, function () {
	      $log.info('Modal dismissed at: ' + new Date());
	    });
	};
	$scope.addSongBtn = function() {
	    $http.post('api/playlists/add/1', $scope.newSong).success(function(data){
	      if(data.error) {
		      $scope.alerts.push({ msg: data.error.text, type: 'danger' });
	      } else if(data.song_id) {
		      $scope.songs.unshift(data);
		      smoothScroll.scrollTo(0);
		      if($scope.random) {
			      $scope.orderShuffle();
		      } else {
			      $scope.orderNormal();
		      }
	      }
	      
	    });
	}
	$scope.closeAlert = function(index) {
    	$scope.alerts.splice(index, 1);
	};

	$scope.logout = function() {
		$scope.user = false; //Aktuell eingeloggter Benutzer
		$scope.user_menu = false;
		$http.get('api/logout/').success(function(data) {
	   })
	}

	$scope.loadCollections = function() {
		console.log('loadCollections');
		$http.get('api/playlists/user/' + $scope.user.id).success(function(data) {
			$scope.collections = data;
			console.log(data);
		}).error(function(data, status, headers, config) {
    		// called asynchronously if an error occurs
    		// or server returns response with an error status.
  		});
	}
});