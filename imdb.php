<?php
/*
	File Created By Pedram Asbaghi
	2018 - 04
	Get Movie Information From IMDb.com
*/

class IMDb {

	public function getMovieInfo($title, $getExtraInfo = true)
	{
		$imdbId = $this->getIMDbIdFromSearch(trim($title));
		if($imdbId === NULL){
			$arr = array();
			$arr['error'] = "No Results Found!";
			return $arr;
		}
		return $this->getMovieInfoById($imdbId, $getExtraInfo);
	}
	

	public function getMovieInfoById($imdbId, $getExtraInfo = true)
	{
		$arr = array();
		$imdbUrl = "http://www.imdb.com/title/" . trim($imdbId) . "/";
		return $this->scrapeMovieInfo($imdbUrl, $getExtraInfo);
	}
	

	private function scrapeMovieInfo($imdbUrl, $getExtraInfo = true)
	{
	$nameExtractor = '/<a.*?href="\/name\/(.*?)["|\/].*?>(.*?)<\/a>/ms';
		$arr = array();
				$html = $this->geturl("${imdbUrl}reference");
		$title_id = $this->match('/<link rel="canonical" href="http:\/\/www.imdb.com\/title\/(tt\d+)\/reference" \/>/ms', $html, 1);
		
		if(empty($title_id) || !preg_match("/tt\d+/i", $title_id)) {
			$arr['error'] = "No Title found on IMDb!";
			return $arr;
		}
		$arr['imdbID']=$title_id;
		$arr['Title']=str_replace('"', '', trim($this->match('/<title>(IMDb \- )*(.*?) \(.*?<\/title>/ms', $html, 2)));
		$arr['imdbRating'] = $this->match('/<\/svg>.*?<\/span>.*?<span class="ipl-rating-star__rating">(.*?)<\/span>/ms', $html, 1);
		$arr['imdbVotes'] = $this->match('/<span class="ipl-rating-star__total-votes">\((.*?)\)<\/span>/ms', $html, 1);
		$arr['Rated'] = $this->match('/<a href="\/preferences\/general" class=>Change View<\/a>.*?<\/span>.*?<hr>.*?<ul class="ipl-inline-list">.*?<li class="ipl-inline-list__item">.*?(G|PG-13|PG-14|PG|R|NC-17|X).*?<\/li>/ms', $html, 1);
		$arr['Runtime'] = trim($this->match('/Runtime<\/td>.*?(\d+) min.*?<\/li>/ms', $html, 1));
		$Genre= $this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Genres<\/td>.*?<td>(.*?)<\/td>/ms', $html, 1), 1);
		$arr['Genre'] = implode(", ",$Genre);
	    $arr['Year']=trim($this->match('/<title>.*?\(.*?(\d{4}).*?\).*?<\/title>/ms', $html, 1));
		$arr['Released'] = $this->match('/releaseinfo">([0-9][0-9]? ([a-zA-Z]*) (19|20)[0-9][0-9])/ms', $html, 1);
		$Actors= $this->match_all_key_value($nameExtractor, $this->match('/Stars:(.*?)<\/ul>/ms', $html, 1));
		$arr['Actors'] = implode(", ",$Actors);
		$directors = $this->match_all_key_value2($nameExtractor, $this->match('/<h4 name="directors" id="directors" class="ipl-header__content ipl-list-title">.*?Directed by.*?<table(.*?)<\/table>/ms', $html, 1));
		$arr['Director'] = implode(", ",$directors);
		$Writer = $this->match_all_key_value($nameExtractor, $this->match('/Writers:.*?<ul(.*?)<\/ul>/ms', $html, 1));
		$arr['Writer'] = implode(", ",$Writer);
		$Country= $this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Country<\/td>(.*?)<\/td>/ms', $html, 1), 1);
		$arr['Country'] = implode(", ",$Country);
        $Language = $this->match_all('/<a.*?>(.*?)<\/a>/ms', $this->match('/Language<\/td>(.*?)<\/td>/ms', $html, 1), 1);
		$arr['Language'] = implode(", ",$Language);
		$arr['Trailer'] = $this->getIMDbTrailer($arr['imdbID']);
		return $arr;
	}
	

	private function getIMDbIdFromSearch($title, $engine = "google"){
		switch ($engine) {
			case "google":  $nextEngine = "bing";  break;
			case "bing":    $nextEngine = "ask";   break;
			case "ask":     $nextEngine = FALSE;   break;
			case FALSE:     return NULL;
			default:        return NULL;
		}
		$url = "http://www.${engine}.com/search?q=imdb+" . rawurlencode($title);
		$ids = $this->match_all('/<a.*?href="http:\/\/www.imdb.com\/title\/(tt\d+).*?".*?>.*?<\/a>/ms', $this->geturl($url), 1);
		if (!isset($ids[0]) || empty($ids[0])) 
			return $this->getIMDbIdFromSearch($title, $nextEngine); 
		else
			return $ids[0]; 
	}
	
	
	//Get Trailer
	private function getIMDbTrailer($titleId){
		require_once('simpleHtmlDoms.php');
		$crawler = file_get_html("http://imdb.com/title/${titleId}");
		foreach($crawler->find("div[class=slate] a[itemprop=trailer]") as $element){
			$trailerID = trim($element->getAttribute("href"));
		}
		if(!isset($trailerID) OR empty($trailerID)){
			$trailer = '';
		} else {
		  preg_match_all("/vi\\d+/i", $trailerID, $matches);
		  $trailerID = implode('', $matches[0]);
		  $src = "http://imdb.com/video/imdb/".$trailerID."/imdb/single?vPage=1";
		  $crawler = file_get_html($src);
		  preg_match_all('/<script class=\"imdb-player-data\" type=\"text\/imdb-video-player-json\">(.*?)<\/script>/', $crawler, $matches);
		  $content = json_decode(trim($matches[1][0]));
		  foreach($content as $element => $value){ // main foreach
			if(preg_match('/videoPlayerObject/i', $element)){ //if one
			  foreach($value as $element => $value){ // foreach one
				foreach ($value as $element => $value) { // foreach tow
				  if(preg_match('/videoInfoList/', $element)){ // if two
					foreach ($value as $element => $value) { // foreach three
					  foreach ($value as $element => $value) { // foreach four
						$values[] = $value;
					  }
					} // end foreach three
				  } // end if two
				} //end foreach two
			  } // end foreach one
			} // end if one
		  } // end main foreach
		  $c = 0;
		  while(TRUE){
			if(preg_match('/video\/(.*?)/', $values[$c])){
			  $trailer = $values[$c+1];
			  break;
			}
			$c++;
		  }
		}
			return $trailer;
	}
	
	
	//Curl
	private function geturl($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		$ip = rand(0,255).'.'.rand(0,255).'.'.rand(0,255).'.'.rand(0,255);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("REMOTE_ADDR: $ip", "HTTP_X_FORWARDED_FOR: $ip"));
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/".rand(3,5).".".rand(0,3)." (Windows NT ".rand(3,5).".".rand(0,2)."; rv:2.0.1) Gecko/20100101 Firefox/".rand(3,5).".0.1");
		$html = curl_exec($ch);
		curl_close($ch);
		return $html;
	}
		private function match_all_key_value( $regex, $str, $keyIndex = 1, $valueIndex = 2 ){
		$arr = array();
		preg_match_all( $regex, $str, $matches, PREG_SET_ORDER );
		foreach( $matches as $m ){
			$arr[$m[$keyIndex]] = $m[$valueIndex];
		}
		return $arr;
	}
	
function match_all_key_value2($regex, $str, $keyIndex = 1, $valueIndex = 2){
	$arr = array();
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        foreach($matches as $m){
			$arr[] = $m[$valueIndex];
        }
		
        return $arr;
    }
	private function match_all( $regex, $str, $i = 0 ){
		if( preg_match_all( $regex, $str, $matches ) === false)
			return '';
		else
			return $matches[$i];
	}
	

	private function match( $regex, $str, $i = 0 ){
		if( preg_match( $regex, $str, $match) == 1 )
			return $match[$i];
		else
			return '';
	}

}
