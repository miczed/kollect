<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE); ini_set('display_errors','On');

require 'auth.php';
require_once 'Services/Soundcloud.php';
require_once 'Slim/Slim.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
$app->config('debug', true);
$app->get('/playlists', 'getPlaylists');
// Gibt alle Playlists zurück

$app->get('/playlists/:key', 'getPlaylistByKey');
//
// Params:
// key: unique_key der Playlist
//
// Return: 
// Gibt den Playlist Header plus die darin enthaltenen Songs zurück
//
$app->post('/playlists/:id','addSong');
//
// Params:
// url: Soundcloud URL des Songs
// id:	Playlist, zu welcher der Song hinzugefügt werden soll.
//
// Return: 
// Gibt den Song zurück
//
$app->post('/playlists','newPlaylist');
//
// Params:
// title: Soundcloud URL des Songs
// unique_key:	Einmaliger key der Playlist für URL
// public: 
//		0:	Playlist ist privat
//		1: 	Playlist ist öffentlich
//
// Return: 
// Gibt den Playlist Header plus die darin enthaltenen Songs zurück
//
$app->post('/collector/:id','addCollectorPost');
//
// Params:
// user_id: ID des Users, welcher hinzugefügt werden soll
// id:	Playlist, zu welcher der Collector hinzugefügt werden soll
//
// Return: 
// Gibt den Benutzer zurück, der hinzugefügt werden soll
//

$app->post('/login','loginUser');


$app->get('/cookie/:key','getUserByLoginKey');


$app->run();



function getPlaylists() {
	$sql = "SELECT * FROM playlist ORDER BY id";
	try {
		$db = getConnection();
		$stmt = $db->query($sql);  
		$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo json_encode($wines);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}
function getPlaylistByKey($key) {
	$sql = "SELECT id FROM playlist WHERE unique_key = '".$key."'";
	try {
		$db = getConnection();
		$stmt = $db->query($sql);  
		$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		getPlaylistById($wines[0]->id);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function newPlaylist() {
	$user_id = secureAction();
	$sql = "SELECT * FROM playlist WHERE unique_key=:unique_key LIMIT 1"; 
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindParam("unique_key",$_POST['unique_key']);
		$stmt->execute();
		$response = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		if ($response == null) {
			$sql = "INSERT INTO playlist (title, unique_key,public, author_id,posted) VALUES (:title, :unique_key, 0, :author_id, :posted)";
			try {
				$db = getConnection();
				$stmt = $db->prepare($sql);  
				$stmt->bindParam("title", $_POST['title']);
				$stmt->bindParam("unique_key", $_POST['unique_key']);
				$stmt->bindParam("author_id", $user_id);
				$stmt->bindParam("posted", date('Y-m-d h:i:s'));
				$stmt->execute();
				
				$playlist_id = $db->lastInsertId();	
				$db = null;	
				getPlaylistById($playlist_id);
				addCollector($user_id,$playlist_id,$user_id);
			} catch(PDOException $e) {
				echo '{"error":{"text":'. $e->getMessage() .'}}'; 
			}
		} else {
			echo '{"error":{"text":'. '"The URL is already taken, please choose a different one."' .'}}'; 
		}
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}

	
}

function getUserInfo($user_id) {
	$sql = "SELECT user_login AS username, id AS id, avatar AS avatar FROM user WHERE id=:id LIMIT 1";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindParam("id",$user_id);
		$stmt->execute();
		$user = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		return $user[0];
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function getPlaylistById($id) {
	$playlist = new stdClass();
	$sql = "SELECT * FROM playlist WHERE id=:id LIMIT 1";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindParam("id",$id);
		$stmt->execute();
		$response = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		
		$playlist = $response[0];
		
		
		$playlist->author = getUserInfo($playlist->author_id);		
		$playlist->collectors = getCollectors($id);
		$sql = "SELECT * FROM v_playlist_song WHERE playlist_id=:id ORDER BY posted DESC";
		try {
			$db = getConnection();
			$stmt = $db->prepare($sql);
			$stmt->bindParam("id",$id);
			$stmt->execute();
			$playlist->songs = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;
			echo json_encode($playlist);
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}'; 
		}

		
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
	}

function getSongById($id) {
	$sql = "SELECT * FROM v_playlist_song WHERE song_id=".$id." LIMIT 1";
	try {
		$db = getConnection();
		$stmt = $db->query($sql);  
		$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo json_encode($wines[0]);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}


function addSong($id) {
	$user_id = secureAction();
	$request = \Slim\Slim::getInstance()->request();
	$song = json_decode($request->getBody());
	
	if (strpos($song->url,"https://soundcloud.com") !== false || strpos($song->url,"http://soundcloud.com") !== false) {
    	// create a client object with your app credentials
		$client_id = 'ddbbf3a736811e6b79a53add7940155b';
		$client = new Services_Soundcloud($client_id,'94168e447ad96ede879422dfc9c15be0','http://kollect.co');
		$track_url = $song->url;
		
		$url = "http://api.soundcloud.com/resolve.json?"
		 . "url=$track_url"
		 . "&client_id=$client_id";
		 
		// Grab the contents of the URL
		
		

		if(get_http_response_code($url) != "404"){
		    $track_json = file_get_contents($url);
		 
			// Decode the JSON to a PHP Object
			$track = json_decode($track_json);
			 
			// Print out the User ID
			$track_id = $track->id;		
			
			$url = "http://api.soundcloud.com/tracks/$track_id.json?"
			. "&client_id=$client_id";
			$track_json = file_get_contents($url);
			$track = json_decode($track_json);
			
			$sql = "INSERT INTO song (title,artist,cover,duration,author_id,sc_id,posted) VALUES (:title, :artist, :cover, :duration, :author_id,:sc_id, :posted) ON DUPLICATE KEY UPDATE";
			$sql .= " title = VALUES(title),";
			$sql .= " artist = VALUES(artist),";
			$sql .= " cover = VALUES(cover),";
			$sql .= " duration = VALUES(duration),";
			$sql .= " author_id = VALUES(author_id),";
			$sql .= " sc_id = VALUES(sc_id),";
			$sql .= " posted = VALUES(posted)";
			try {
				$db = getConnection();
				$stmt = $db->prepare($sql);  
				$stmt->bindParam("title", $track->title);
				$stmt->bindParam("artist", $track->user->username);
				$stmt->bindParam("cover", $track->artwork_url);
				$stmt->bindParam("duration", $track->duration);
				$stmt->bindParam("author_id", $user_id);
				$stmt->bindParam("sc_id", $track->id);
				$stmt->bindParam("posted", date('Y-m-d h:i:s'));
				$stmt->execute();
				$song_id = $db->lastInsertId();
				$db = null;
				
				if(addSongToPlaylist($song_id,$id)) {
					getSongById($song_id);
				};
				
				
			} catch(PDOException $e) {
				echo '{"error":{"text":'. $e->getMessage() .'}}'; 
			}

			
			
		}else{
		    echo '{"error":{"text":'. '"No Track found. Please check your URL."' .'}}';

		}
		
		
		
		
		// now that we have the track id, we can get a list of comments, for example
		/*foreach (json_decode($client->get('tracks/' . $track->id . 'comments')) as $c)
		    print 'Someone said: ' . $c->body . ' at ' . $c->timestamp . "\n";*/
		    
		
		
		/*
		$sql = "INSERT INTO users (username, first_name, last_name, address) VALUES (:username, :first_name, :last_name, :address)";
		try {
			$db = getConnection();
			$stmt = $db->prepare($sql);  
			$stmt->bindParam("username", $user->username);
			$stmt->bindParam("first_name", $user->first_name);
			$stmt->bindParam("last_name", $user->last_name);
			$stmt->bindParam("address", $user->address);
			$stmt->execute();
			$user->id = $db->lastInsertId();
			$db = null;
			echo json_encode($user); 
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}'; 
		}*/

    	
	} else {
		echo '{"error":{"text":'. '"Please enter a valid Soundcloud URL."' .'}}';
	}
	
}

function addSongToPlaylist($song_id,$playlist_id) {
	$user_id = secureAction();
	$sql = "SELECT * FROM playlist_song WHERE playlist_id=:playlist_id AND song_id =:song_id LIMIT 1"; 
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindParam("playlist_id",$playlist_id);
		$stmt->bindParam("song_id",$song_id);
		$stmt->execute();
		$response = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		if ($response == null) {
			$sql = "INSERT INTO playlist_song (song_id, playlist_id, author_id,posted) VALUES (:song_id, :playlist_id, :author_id, :posted)";
			try {
				$db = getConnection();
				$stmt = $db->prepare($sql);  
				$stmt->bindParam("song_id", $song_id);
				$stmt->bindParam("playlist_id", $playlist_id);
				$stmt->bindParam("author_id", $user_id);
				$stmt->bindParam("posted", date('Y-m-d h:i:s'));
				$stmt->execute();
				$db = null;	
				return true;	
			} catch(PDOException $e) {
				echo '{"error":{"text":'. $e->getMessage() .'}}'; 
			}
		} else {
			echo '{"error":{"text":'. '"The song is already in the playlist."' .'}}'; 
		}
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
	
}
function addCollectorPost($id) {
	$author_id = secureAction();
	echo addCollector($id,$_POST['playlist_id'],$author_id);
}
function addCollector($user_id,$playlist_id,$author_id) {
	$sql = "SELECT * FROM playlist_collector WHERE playlist_id=:playlist_id AND user_id =:user_id LIMIT 1"; 
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindParam("playlist_id",$playlist_id);
		$stmt->bindParam("user_id",$user_id);
		$stmt->execute();
		$response = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		if ($response == null) {
			$sql = "INSERT INTO playlist_collector (user_id, playlist_id, author_id,posted) VALUES (:user_id, :playlist_id, :author_id, :posted)";
			try {
				$db = getConnection();
				$stmt = $db->prepare($sql);  
				$stmt->bindParam("user_id", $user_id);
				$stmt->bindParam("playlist_id", $playlist_id);
				$stmt->bindParam("author_id", $author_id);
				$stmt->bindParam("posted", date('Y-m-d h:i:s'));
				$stmt->execute();
				$db = null;	
				return json_encode(getUserInfo($user_id));	
			} catch(PDOException $e) {
				return '{"error":{"text":'. $e->getMessage() .'}}'; 
			}
		} else {
			return '{"error":{"text":'. '"The user is already in the list of collectors."' .'}}'; 
		}
	} catch(PDOException $e) {
		return '{"error":{"text":'. $e->getMessage() .'}}'; 
	}

}

function getCollectors($playlist_id) {
	$collectors = array();
	$sql = "SELECT * FROM playlist_collector WHERE playlist_id=:playlist_id ORDER BY posted DESC";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql); 
		$stmt->bindParam("playlist_id", $playlist_id);
		$stmt->execute();
		$users = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		foreach($users as $user) {
			$added = $user->posted;
			$author_id = $user->author_id;
			$collector = getUserInfo($user->user_id);
			$collector->added = $added;
			$collector->author_id = $author_id;
			$collectors[] = $collector;
		}
		return $collectors;
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}
/*
function updateUser($id) {
	$request = Slim::getInstance()->request();
	$user = json_decode($request->getBody());
	$sql = "UPDATE users SET username=:username, first_name=:first_name, last_name=:last_name, address=:address WHERE id=:id";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);  
		$stmt->bindParam("username", $user->username);
		$stmt->bindParam("first_name", $user->first_name);
		$stmt->bindParam("last_name", $user->last_name);
		$stmt->bindParam("address", $user->address);
		$stmt->bindParam("id", $id);
		$stmt->execute();
		$db = null;
		echo json_encode($user); 
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function deleteUser($id) {
	$sql = "DELETE FROM users WHERE id=".$id;
	try {
		$db = getConnection();
		$stmt = $db->query($sql);  
		$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo json_encode($wines);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}*/

function getUserByLoginKey($key) {
	secureAction();
	$sql = "SELECT user.user_login as user_login, user.id as id, user.avatar as avatar FROM `ticket` JOIN user ON (ticket.user_id = user.id) WHERE ticket.uid = :uid";
	try {
		$db = getConnection();
		$stmt = $db->prepare($sql);
		$stmt->bindParam("uid",$key);
		$stmt->execute();
		$user = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo json_encode($user[0]);
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}'; 
	}
}

function loginUser() {
	

	$request = \Slim\Slim::getInstance()->request();
	$user = json_decode($request->getBody());
	
	
	if (isset($user->name) && isset($user->password)) {
		$password = md5($user->password.$user->name);
		
		$sql = "SELECT id,user_login,avatar FROM user WHERE  user_login=:username AND user_pass=:password LIMIT 1";
		try {
			$db = getConnection();
			$stmt = $db->prepare($sql);  
			$stmt->bindParam("username", $user->name);
			$stmt->bindParam("password", $password);
			$stmt->execute();
			$response = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;
			//echo json_encode($user); 
			if ($response != null) {
				$ticket = array(
					'uid' => uuidSecure(),
					'user_id' => $response[0]->id,
					'expires' => time() + 24 * 60 + 60
				);
				
				//Delete old tickets before creating a new one
				$sql = "DELETE FROM ticket WHERE user_id=".$response[0]->id;
				try {
					$db = getConnection();
					$stmt = $db->query($sql);  
					$db = null;
				} catch(PDOException $e) {
					echo '[{"error":{"text":'. $e->getMessage() .'}}]'; 
				}

				
				$sql = "INSERT INTO ticket (uid, expires, user_id) VALUES (:uid, :expires, :user_id)";
				try {
					$db = getConnection();
					$stmt = $db->prepare($sql);  
					$stmt->bindParam("uid", $ticket['uid']);
					$stmt->bindParam("user_id", $ticket['user_id']);
					$stmt->bindParam("expires", $ticket['expires']);
					$stmt->execute();
					$ticket_id = $db->lastInsertId();
					$db = null;
				} catch(PDOException $e) {
					echo '{"error":{"text":'. $e->getMessage() .'}}'; 
				}
	
				//$app->setCookie('ticket',  $ticket['id']);
				setcookie('ticket', $ticket['uid'],$ticket['expires'], "/", ".kollect.co");
				
				echo json_encode($response[0]);
			} else {
				echo '{"error":{"text":'. '"Wrong Username or Password. Please try again."' .'}}';
			}
			
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}'; 
		}
	}

		
};

function secureAction()
{
	if (isset($_COOKIE['ticket']))
	{
		//fetch the ticket from the db
		

		$sql = "SELECT * FROM ticket WHERE uid=:uid LIMIT 1";
		try {
			$db = getConnection();
			$stmt = $db->prepare($sql);
			$stmt->bindParam("uid",$_COOKIE['ticket']);
			$stmt->execute();
			$ticket = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;
			
			
			//delete if expired
			if(!$ticket) {
				echo "FAILED";
				//no current log in
				http_response_code(401);
				die();	
			}
			if ($ticket[0]->expires < time()) {
				//Delete expired ticket
				$sql = "DELETE FROM ticket WHERE uid=:uid";
				try {
					$db = getConnection();
					$stmt = $db->prepare($sql);
					$stmt->bindParam("uid",$_COOKIE['ticket']);
					$stmt->execute();
					$db = null;	
				} catch(PDOException $e) {
					echo '{"error":{"text":'. $e->getMessage() .'}}'; 
				}
				echo "EXPIRED";
				http_response_code(401);
				die();
			}
			else {
				//update if ticket has aged
				if ($ticket[0]->expires - 10 * 60 < time()) {
					$ticket[0]->expires = time() + 24 * 60 * 60;
					$sql = "UPDATE ticket SET expires=".$ticket[0]->expires." WHERE uid=:uid";
					try {
						$db = getConnection();
						$stmt = $db->prepare($sql);
						$stmt->bindParam("uid",$_COOKIE['ticket']);
						$stmt->execute();
						$db = null;	
					} catch(PDOException $e) {
						echo '{"error":{"text":'. $e->getMessage() .'}}'; 
					}
				}
	
				//current log in active
				return $ticket[0]->user_id;
			}
					
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}'; 
		}		
		
	}

	echo "FAILED";
	//no current log in
	http_response_code(401);
	die();
}


function getConnection() {
  $dbhost="localhost";
  $dbuser="web94";
  $dbpass="GZ082LjO";
  $dbname="usr_web94_13";
  $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass,array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));  
  $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $dbh;
}
function get_http_response_code($url) {
    $headers = get_headers($url);
    return substr($headers[0], 9, 3);
}

?>