<?php


class collectSiteFutaba extends collectLib{


function collect($arg){

  //ふたば系

  //ふたば掲示板のタイトルが格納される正規表現を指定する。
  //ふたばはタイトルよりも※1のbody見たほうがいいよ

  //ふたば掲示板の「返信」リンクの名称を指定する。この名称が含まれるリンクを巡回するようになってるよ
  $this->conf['futaba_res'] = "返信";
  $this->conf['futaba_res_preg'] = "#2chan\.net\/b\/res\/(.*?)\.htm#";
  $this->conf['cs']['futaba'] = "sjis-win";
  $imgnum_min =  $arg['imgnum_min'];//貼られた画像がこれ以下のスレはスキップ

  $this->dl->service = $arg['service'];
  $keyword = $arg['keyword'];
  $ngword = $arg['ngword'];
  $url = $arg['url'];



//////// //////// //////// //////// //////// //////// //////// //////// //////// //////// 

try{
  //$this->dl->service = 'futaba';

  //文字コードをセット
  $this->dl->cs_web = $this->conf['cs']['futaba'];
	
  //ダウンロード
  $top_page = $this->dl->download($url);
  $this->e_web2loc($top_page,$this->conf['cs']['futaba']);
  if($top_page == false){
    throw new Exception("トップページの取得に失敗しました: {$url}");
  }

  //トップページのリンク一覧取得
  $link_list = $this->generate_link_list($url,$top_page);
  if(count($link_list)<=0){
    throw new Exception("トップページ: リンク一覧の取得に失敗しました: {$url}");
  }
  $next_page_data = array();
  $next_page_data[0] = $link_list;

  //数字オンリーのリンクを「次ページ」と見なしながら取得する。
  //たまに誤爆があるかもしれないが広く受けるためです。
  foreach($link_list as $link){
    $title = $link[0];
    if(is_numeric($title)){
      $page_number = intval($title);
      $next_page_link_list = array();
      $next_page_url = $link[1];
      $next_page = $this->dl->download($next_page_url);
      //ふたばはsjisなので内部エンコーディングに変換
      $this->e_web2int($next_page,$this->conf['cs']['futaba']);
      $this->err("ftb:get page[0-8] : {$next_page_url}\n");
      if($next_page != false){
        //ページ内リンクを抽出、リンクリストに追加
        $next_page_link_list = $this->generate_link_list($next_page_url,$next_page);
        if($next_page_link_list !== false){
          $next_page_data[$page_number] = $next_page_link_list;
        }
      }
    }
  }
  $this->err("ftb:got: ftb link url list all\n");
  
  //リンクリストに対し
  foreach($next_page_data as $page_number => $link_list){

    foreach($link_list as $link){

      //リンクタイトルの取得
      $title = trim($link[0]);

      //要素が返信、レスへのリンクでなければ弾く
      if(preg_match($this->conf["futaba_res_preg"], (string) $link[1]) < 1){continue;}

      //スレッドをダウンロード
      $thread_url = html_entity_decode($link[1]);
      $this->err("ftb:th:getTopic:{$thread_url}\n");
      $thread_data = $this->dl->download($thread_url);
      if($thread_data == false){continue;}
      
      //DL条件とのマッチ
      //ふたばはsjisなので内部エンコーディングに変換
      $this->e_web2int($thread_data,$this->conf['cs']['futaba']);
      //rgfx::add
      $thread_data=preg_replace("/[\r\n\s]/","",$thread_data);
      $thread_data=str_replace("<br>","_",$thread_data);
      //スレタイというよりもスレ立て時コメントをタイトルとする
      if(preg_match("/<blockquote>(.*?)<\/blockquote>/",$thread_data,$matches)){
        $thread_title = $matches[1];
        $thread_title=mb_substr($thread_title,0,30,ENC_LOC);//rgfx::add
      }else{
        $thread_title = "無題";
      }
      //$this->err("ftb:th:getFirstComment:{$thread_title}\n");
      //ついでにスレ立て日時、スレ立て番号もゲット。
      preg_match("/#117743\'\>(.*?)\<blockquote\>/",$thread_data,$matches);
      $thread_moretitle = $matches[1];
      $thread_moretitle = preg_replace("/\<b\>.*?\<\/font\>/","",$thread_moretitle);
      $thread_moretitle = preg_replace("/\<a.*?$/","",$thread_moretitle);
      $thread_moretitle = preg_replace("/\s/","",$thread_moretitle);
      preg_match("/No.(.*?)$/",$thread_moretitle,$matches);
      $thread_num = $matches[1];
      preg_match("/(.*?)\(.*?$/",$thread_moretitle,$matches);
      $thread_date = $matches[1];
      $thread_date = "20".preg_replace("/[^0-9]/","_",$thread_date);
      $thread_title = "{$thread_title}_{$thread_num}";
      
      $keyword_flag = true;
//      //キーワード指定がある場合はスレッドタイトルが検索ワードにひっかかってるかをキーワードフラグに保存
//      if($keyword != null){
//        foreach($keyword as $needle){
//          if($this->dl->indexOf($thread_title,$needle,0)!==false){
//            $keyword_flag = true;
//          }
//        }
//      }else{
//        $keyword_flag = true;
//      }
      //NGワード指定がある場合はスレッドタイトルが検索ワードにひっかかってるかをキーワードフラグに保存
      if($ngword != null){
        foreach($ngword as $needle){
          if($this->dl->indexOf($thread_title,$needle,0)!==false){
            $keyword_flag = false;
          }
        }
      }else{
        $keyword_flag = true;
      }
      //全部DLさせてみっか。
      if(!$keyword_flag){
        $this->err("ftb:th:deny \n");
        continue;
      }

      //スレのリンク一覧を作成
      $this->err("ftb:th:match:getLink: {$thread_title}\n");
      $thread_page_html_src = $this->dl->download($thread_url);
      //sjisなのでphp内部エンコードに変換
      $thread_page_html_enc=$thread_page_html_src;
      $this->e_web2int($thread_page_html_enc,$this->conf['cs']['futaba']);
      $thread_page_link_list = $this->generate_link_list(
        $next_page_url,
        $thread_page_html_enc
      );
      if(count($thread_page_link_list)<1){continue;}
      
      //露払い
      $imglinkcount=0;
      $tmp=array();
      $tmp_src=$thread_page_link_list;
      foreach($tmp_src as $k2=>$thread_page_link){
        $thread_page_link_url = $thread_page_link[1];
        //スキップURL
        if(preg_match("/2chan\.net/",$thread_page_link_url)==0){
          continue;
        }
        if(preg_match("/futaba\.php/",$thread_page_link_url)>0){
          continue;
        }
        if(preg_match("/sb\.php/",$thread_page_link_url)>0){
          continue;
        }
        if(preg_match("#/junbi/#",$thread_page_link_url)>0){
          continue;
        }
        if(preg_match("#jpg|gif|png#",$thread_page_link_url)<1){
          continue;
        }
        #重複回避
        $tmp[md5($thread_page_link_url)]=array('',$thread_page_link_url);
      }
      #画像の枚数が[$imgnum_min]に満たないスレなぞいらぬ
      if(count($tmp) < $imgnum_min){
        continue;
      }else{
        //dbg
        //$this->err(print_r($tmp,true));continue;
      }
      $thread_page_link_list = $tmp;
      
      //リンクリスト抽出おｋ
      //あとはひたすらダウンロード
      $loop=0;
      $loopmax = count($thread_page_link_list);
      foreach($thread_page_link_list as $k0 => $thread_page_link){
        //if($loop>5){continue;}
        //遷移先のURLを取得
        $thread_page_link_url = $thread_page_link[1];
        //$this->err("ftb:th:match:img:embed: {$thread_page_link_url}\n");
        
        //既に取得した画像ならスキップ
        if(!$this->dl->save_path_detect($thread_page_link_url,
        	$thread_title,$file_ext,$thread_date)){
          continue;
        }
        
        //画像DL
        $url_data = $this->dl->download($thread_page_link_url,"GET");
        if($url_data === false){
          continue;
        }
        //MIME-typeを取得
        $mime_type = $this->dl->get_mime_type($url_data);
        //$this->err("ftb:th:match:img:mime: {$mime_type}\n");
        //ダウンロード対象のMIME-typeだったら保存する
        if(!array_search($mime_type,$this->conf['file_type'])){
          $this->err("ftb:mimetype not matches. [{$mime_type}]\n");
          continue;
        }
        //データを保存する
        $save_file = $this->dl->save_file($thread_page_link_url,
        	$thread_title,$url_data,$file_ext,$thread_date);
        if($save_file == true){
          //$this->err("ftb:th:onJobTopic:{$thread_url}\n");
          //$this->err("ftb:th:onJobFirstComment:{$thread_title}\n");
          $this->err("ftb:saved:{$thread_page_link_url} ({$loop}/{$loopmax})"
            ." / wait:({$this->conf['retry_wait']}) \n");
          //$this->err("  -- thread_title {$thread_title}\n");
          //wait
          sleep($this->conf['retry_wait']);
          $loop++;
        }
      }
      
      //スレ自体を保存
      $thread_page_html_src=preg_replace(
        "/<a.href=\"http\:\/\/.*?\.2chan\.net.*?\/b\/src\//",
        '<a href="',$thread_page_html_src);
      $thread_page_html_src=preg_replace(
        "/<img.src=http\:\/\/.*?\.2chan\.net.*?\/b\/thumb\//",
        '<img src=',$thread_page_html_src);
      $thread_page_html_src=preg_replace(
        "/s\.jpg.border=0/",
        '.jpg border=0',$thread_page_html_src);
      $thread_page_html_src=preg_replace(
        "/<base\shref.*?>/",
        "<!-- {$thread_url} -->",$thread_page_html_src);
      $thread_page_html_src=preg_replace(
        "/[\r\n]/",
        "",$thread_page_html_src);
      $thread_page_html_src=preg_replace(
        "/<table><tr><td><center>.*?<\/center><\/td><\/tr><\/table>/",
        "",$thread_page_html_src);
      $thread_page_html_src=preg_replace(
        "#http\:\/\/#",
        "",$thread_page_html_src);
      $res = $this->dl->save_file("/index",
      	$thread_title,$thread_page_html_src,'html',$thread_date);
      
    }
  }

  $this->err("ftb:{$url}の巡回が完了しました\n");

}catch(Exception $e){

  $this->err("ftb:{$url}の巡回中にエラーが発生しました\n");
  $this->err("ftb:".$e->getMessage()."\n");

}

  return;
}

}


