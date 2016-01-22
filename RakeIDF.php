<?php
# Implementation of RAKE - Rapid Automatic Keyword Extraction algorithm
# as described in:
# Rose, S., D. Engel, N. Cramer, and W. Cowley (2010). 
# Automatic keyword extraction from indi-vidual documents. 
# In M. W. Berry and J. Kogan (Eds.), Text Mining: Applications and Theory.unknown: John Wiley and Sons, Ltd.
# Converted to PHP by Ross Scrivener ross@scrivna.com

class RakeIDF {

	function load_stop_words(){
		$auto  = array();
		
		$stopwords = explode(" ","a a's able about above according accordingly across actually after afterwards again against ain't all allow allows almost alone along already also although always am among amongst an and another any anybody anyhow anyone anything anyway anyways anywhere apart appear appreciate appropriate are aren't around as aside ask asking associated at available away awfully b be became because become becomes becoming been before beforehand behind being believe below beside besides best better between beyond both brief but by c c'mon c's came can can't cannot cant cause causes certain certainly changes clearly co com come comes concerning consequently consider considering contain containing contains corresponding could couldn't course currently d definitely described despite did didn't different do does doesn't doing don't done down downwards during e each edu eg eight either else elsewhere enough entirely especially et etc even ever every everybody everyone everything everywhere ex exactly example except f far few fifth first five followed following follows for former formerly forth four from further furthermore g get gets getting given gives go goes going gone got gotten greetings h had hadn't happens hardly has hasn't have haven't having he he's hello help hence her here here's hereafter hereby herein hereupon hers herself hi him himself his hither hopefully how howbeit however i i'd i'll i'm i've ie if ignored immediate in inasmuch inc indeed indicate indicated indicates inner insofar instead into inward is isn't it it'd it'll it's its itself j just k keep keeps kept know knows known l last lately later latter latterly least less lest let let's like liked likely little look looking looks ltd m mainly many may maybe me mean meanwhile merely might more moreover most mostly much must my myself n name namely nd near nearly necessary need needs neither never nevertheless new next nine no nobody non none noone nor normally not nothing novel now nowhere o obviously of off often oh ok okay old on once one ones only onto or other others otherwise ought our ours ourselves out outside over overall own p particular particularly per perhaps placed please plus possible presumably probably provides q que quite qv r rather rd re really reasonably regarding regardless regards relatively respectively right s said same saw say saying says second secondly see seeing seem seemed seeming seems seen self selves sensible sent serious seriously seven several shall she should shouldn't since six so some somebody somehow someone something sometime sometimes somewhat somewhere soon sorry specified specify specifying still sub such sup sure t t's take taken tell tends th than thank thanks thanx that that's thats the their theirs them themselves then thence there there's thereafter thereby therefore therein theres thereupon these they they'd they'll they're they've think third this thorough thoroughly those though three through throughout thru thus to together too took toward towards tried tries truly try trying twice two u un under unfortunately unless unlikely until unto up upon us use used useful uses using usually uucp v value various very via viz vs w want wants was wasn't way we we'd we'll we're we've welcome well went were weren't what what's whatever when whence whenever where where's whereafter whereas whereby wherein whereupon wherever whether which while whither who who's whoever whole whom whose why will willing wish with within without won't wonder would would wouldn't x y yes yet you you'd you'll you're you've your yours yourself yourselves z zero copyright contact email click 2010 2011 2012 2013 2014 phone telephone blog news offer offers means legal unlike taking now's describe describes &");
		
		return array_merge($stopwords, $auto);
	}
	
	
	function load_idf(){
		return array_slice(json_decode(file_get_contents(dirname(__FILE__).'/idf.json'), true),0,25000);
	}
	
	// split words and return greater than min length
	function separate_words($str, $min_length){
		//$words = str_word_count($str, 1, '0..9%');
		$words = preg_split('/\b/', $str);
		foreach ($words as $key => $word){
			if (strlen($word) <= $min_length){
				unset($words[$key]);
			}
		}
		return array_values($words);
	}
	
	// return a list of sentences.
	function split_sentences($str){
		$str = str_ireplace(' - ', '. ', $str);
		//$sentences = array_filter(preg_split('/[\.\!\?,;\:\(\)\t"\|\[\]\/\\\]/', $str));
		$sentences = array_filter(preg_split('/[\.\!\?,;\:\(\)\t"\|\[\]\/\\\]/', $str));
		array_walk($sentences, function(&$v, $i){
			$v = trim(preg_replace('!\s+!', ' ', $v));
		});
		return array_filter($sentences);
	}
	
	function build_stop_word_regex(){
		$stopwords = $this->load_stop_words();
		// sort by length so earlier occurrences don't replace longer
		usort($stopwords, function($a,$b){
		    return mb_strlen($b)-mb_strlen($a);
		});
		
		$stop_word_regex_list = array();
		foreach ($stopwords as $word){
			$word_regex = preg_quote($word);
			$stop_word_regex_list[] = preg_quote($word_regex);
		}
		
		// break on words that aren't preceeded by an apostrophe or followed by a dash or apostrophe
		$break = '(?<!\')\b(?![-\'])';
		$r = '@'.$break.'('.implode('|', $stop_word_regex_list).')'.$break.'@i';
		return $r;
	}
	
	function generate_candidate_keywords($sentence_list, $stopword_pattern){
		$phrase_list = array();
		foreach ($sentence_list as $sentence){
			$phrases = array_filter(preg_split($stopword_pattern, $sentence));
			foreach ($phrases as $phrase){
				if(count($this->separate_words($phrase, 1))==0) continue;
				
				$phrase = trim(strtolower(preg_replace('/(\s)+/', ' ', $phrase)), " -'");
				if ($phrase!='' && !is_numeric(str_replace(' ','',$phrase))){
					$phrase_list[] = $phrase;
				}
			}
		}
		return $phrase_list;
	}
	
	function calculate_word_scores($phrase_list){
		
		$word_frequency = array();
		$word_positions = array();
		$word_idf = $this->load_idf();
		
		$i=0;
		foreach ($phrase_list as $phrase){
			$word_list = $this->separate_words($phrase, 1);
			foreach ($word_list as $word){
				$word_frequency[$word] = isset($word_frequency[$word]) ? $word_frequency[$word]+1 : 1;
				$word_positions[$word] = isset($word_positions[$word]) ? $word_positions[$word] : ++$i;
			}
		}
		
		$word_score = array('frequency'=>array(), 'idf'=>array(), 'position'=>array());
		foreach($word_frequency as $word => $score){
			$word_score['frequency'][$word] = $word_frequency[$word];
			$word_score['position'][$word] = $word_positions[$word];
			
			// boost words not in idf, 3 is magic number from idf file
			$word_score['idf'][$word] = ($word_frequency[$word] / count($word_frequency)) * (isset($word_idf[$word]) ? $word_idf[$word] : 3);
		}
		
		//pr($word_score); exit;
		return $word_score;
	}
	
	function generate_candidate_keyword_scores($phrase_list, $word_score){
		
		$keyword_candidates = array('frequency'=>array(), 'idf'=>array(), 'position'=>array(), 'merged'=>array());
		foreach ($phrase_list as $phrase){
			$word_list = $this->separate_words($phrase, 1);
			
			$keyword_candidates['frequency'][$phrase]	= 0;
			$keyword_candidates['idf'][$phrase]			= 0;
			$keyword_candidates['position'][$phrase]	= 0;
			
			foreach ($word_list as $word){
				$keyword_candidates['frequency'][$phrase]	+= $word_score['frequency'][$word];
				$keyword_candidates['idf'][$phrase]			+= $word_score['idf'][$word];
				$keyword_candidates['position'][$phrase]	+= $word_score['position'][$word];
			}
			
			$den = max(1, count($word_list)/1.5);
			$keyword_candidates['frequency'][$phrase] /= $den;
			$keyword_candidates['idf'][$phrase] 		/= $den;
			$keyword_candidates['position'][$phrase] 	/= $den;
		}
		
		// don't really need sorting but useful for debug
		//arsort($keyword_candidates['frequency']);
		//arsort($keyword_candidates['idf']);
		//asort($keyword_candidates['position']);
		
		// normalise scores in 0-1 range, higher = better
		$max = max($keyword_candidates['frequency']);
		foreach ($keyword_candidates['frequency'] as $phrase => $score){
			$keyword_candidates['frequency'][$phrase] = round($score/$max,5);
		}
		$max = max($keyword_candidates['idf']);
		foreach ($keyword_candidates['idf'] as $phrase => $score){
			$keyword_candidates['idf'][$phrase] = round($score/$max,5);
		}
		$max = max($keyword_candidates['position']);
		foreach ($keyword_candidates['position'] as $phrase => $score){
			$keyword_candidates['position'][$phrase] = 1-round($score/$max, 5);
		}
		
		//pr($keyword_candidates); exit;
		
		// calculate merged
		foreach ($phrase_list as $phrase){
			$keyword_candidates['merged'][$phrase] = 0;
			$keyword_candidates['merged'][$phrase]+= 1.0 * $keyword_candidates['frequency'][$phrase];
			$keyword_candidates['merged'][$phrase]+= 1.0 * $keyword_candidates['idf'][$phrase];
			$keyword_candidates['merged'][$phrase]+= 1.5 * $keyword_candidates['position'][$phrase];
		}
		arsort($keyword_candidates['merged']);
		
		$max = max($keyword_candidates['merged']);
		foreach ($keyword_candidates['merged'] as $phrase => $score){
			$keyword_candidates['merged'][$phrase] = number_format($score/$max,5);
		}
		
		return $keyword_candidates['merged'];
	}
	
	function normalize_special_characters( $str ) { 
		
		// convert our string to utf-8
		$str = mb_convert_encoding($str, mb_detect_encoding($str), 'UTF-8');
		
		// decode html entities in to utf-8 strings then remove html tags
		$str = strip_tags(html_entity_decode($str, ENT_QUOTES, 'UTF-8'));
		
		// convert some special characters that we want to keep to their ascii equivalents
		$convert = array(
		    "\xC2\xAB"     => '"', // « (U+00AB) in UTF-8
		    "\xC2\xBB"     => '"', // » (U+00BB) in UTF-8
		    "\xE2\x80\x98" => "'", // ‘ (U+2018) in UTF-8
		    "\xE2\x80\x99" => "'", // ’ (U+2019) in UTF-8
		    "\xE2\x80\x9A" => "'", // ‚ (U+201A) in UTF-8
		    "\xE2\x80\x9B" => "'", // ‛ (U+201B) in UTF-8
		    "\xE2\x80\x9C" => '"', // “ (U+201C) in UTF-8
		    "\xE2\x80\x9D" => '"', // ” (U+201D) in UTF-8
		    "\xE2\x80\x9E" => '"', // „ (U+201E) in UTF-8
		    "\xE2\x80\x9F" => '"', // ‟ (U+201F) in UTF-8
		    "\xE2\x80\xB9" => "'", // ‹ (U+2039) in UTF-8
		    "\xE2\x80\xBA" => "'", // › (U+203A) in UTF-8
		    "→" => "",
		    "–" => "-",
		    "…" => "..."
		);
		$str = strtr($str, $convert);
		
		
		return $str;
		
		// replace anything that isn't character, digit, puntuation, mark, maths, currency symbol, special mark
		// http://www.regular-expressions.info/unicode.html
		$str = preg_replace('/[^\p{L}\p{N}\p{P}\p{M}\p{Sm}\p{Sc}\p{Sk}]++/u', ' ', $str);
		
		
		// replace multiple spaces with a single
		$str = trim(preg_replace('/\s+/', ' ', $str));
		
		// remove non utf8 chars
		/*$str = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
									'|[\x00-\x7F][\x80-\xBF]+'.
									'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
									'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
									'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
									'', $str);*/
		
		return $str; 
	}
	
	function __construct(){
		$this->__stop_words_pattern = $this->build_stop_word_regex();
	}
	
	function run($text){
		
		$text = $this->normalize_special_characters($text);
		$sentence_list = $this->split_sentences($text);
		
		$phrase_list = $this->generate_candidate_keywords($sentence_list, $this->__stop_words_pattern);
		$word_scores = $this->calculate_word_scores($phrase_list);
		$keyword_candidates = $this->generate_candidate_keyword_scores($phrase_list, $word_scores);
		return $keyword_candidates;
	}
	
	/* method for generating idf.json file
	 pass an array of strings to train on
	 
	 	$r = new RakeIDF;
		$r->create_idf([
			'hello world',
			'world at one',
			'be one with yourself',
			'welcome me to the world'
		]);
	*/
	
	function create_idf($strings){
			
		// split on word boundaries, then count words
		$idf = array();
		foreach ($strings as $str){
			$str = $this->normalize_special_characters(strtolower($str));
			$words = preg_split('/[\s\*\.\!\?,;\:\(\)\t"\|\[\]]/', $str);
			array_walk($words,function(&$v){ $v = trim($v,"'-"); });
			$words = array_filter($words);
			$counts = array_count_values($words);
			$words = array_keys($counts);
			
			foreach ($words as $word){
				@$idf[$word]++;
			}
		}
		
		foreach ($idf as $word => $occurrences){
			//$idf[$word] = round(log(count($files) / $occurrences),6);
			$idf[$word] = round(log(((count($strings)-$occurrences)+.5) / ($occurrences+.5)),6); // normalised idf
		}
		
		// scale values to fit in a range
		$min = min($idf);
		$max = max($idf);
		$new_min = max(0, $min);
		$new_max = $max;
		foreach ($idf as $i => $v){
			$idf[$i] = ((($new_max - $new_min) * ($v - $min)) / ($max - $min)) + $new_min;
		}
		
		asort($idf);
		
		$idf = array_slice($idf, 0, 25000);
		file_put_contents(dirname(__FILE__).'/idf.json', json_encode($idf));
	}
}
?>