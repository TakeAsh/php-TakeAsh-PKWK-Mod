<?php
//////////////////////////////////////////////////////////////////////
// replace_tak.inc.php
//       based on replace.inc.php by teanan / Interfair Laboratory 2006.
//       modified by TakeAsh
// 全てのページに対して、指定の単語を置換する

// 更新履歴
// -2008-03-07 ver 0.1a	正規表現対応、変換対象ページリストアップ
// -2006-07-28 ver 0.1	PukiWiki-1.4.7対応版
// -2004-09-02 ver 0.0	暫定版
//
// 使用方法
//   http://hogehoge/pukiwiki.php?plugin=replace_tak
//   検索文字列、置換文字列とパスワードを入力してPreviewを押す。
//   対象となるページ一覧が出るので、間違いなければReplaceを押す。
//   オプションとして、ページ名の絞り込みを指定できる。

function plugin_replace_tak_action()
{
	global $script, $post;

	$pass    = isset($post['pass'])    ? $post['pass']    : NULL;
	$prefix  = isset($post['prefix'])  ? $post['prefix']  : NULL;
	$search  = isset($post['search'])  ? $post['search']  : NULL;
	$replace = isset($post['replace']) ? $post['replace'] : NULL;
	$act     = isset($post['act'])     ? $post['act']     : NULL;
	$preserveTimeStamp = array_key_exists('preserveTimeStamp' ,$post) ? $post['preserveTimeStamp'] : NULL;
	$changedpages = array();
	$body = '';
	$replace_real = stripcslashes( $replace );
	$preserveTimeStamp = ( $preserveTimeStamp != '' ) ? TRUE : FALSE ;

	$islogin = pkwk_login($pass);

	// パスワード一致
	if ( $search!='' && $islogin )
	{
		$pages = get_existpages();
		if ( $prefix != NULL ){
			$tmppages = array();
			foreach( $pages as $page ){
				if ( preg_match( $prefix, $page ) ){
					$tmppages[] = $page;
				}
			}
			$pages = $tmppages;
		}
		natsort($pages);
		foreach ($pages as $page)
		{
			$postdata = '';
			$count = 0;
			$postdata_old = join('',get_source($page));
			// キーワードの置換
			$postdata = preg_replace( $search, $replace_real, $postdata_old, -1, $count );
			if ( $count > 0 ){
				$changedpages[] = htmlspecialchars($page);
				if ( $act == 'Replace' ){
					set_time_limit(30);
					page_write( $page, $postdata, $preserveTimeStamp );
				}
			}
		}
		if ( $act == 'Replace' ){
			$body = '<p>Completed.</p>';
		}
	}

	if ( $pass !== NULL && !$islogin ){
		$body .= "<p><strong>Password error.</strong></p>\n";
	}
	$replacebutton = ( $islogin && count($changedpages) > 0 && ( $act == 'Preview' || $act == 'Replace' ) ) ? 
		'<input type="submit" name="act" value="Replace" />' : '' ;
	$statTimeStamp = ($preserveTimeStamp) ? 'checked' : '' ;
	$body .= <<<EOD
<p>Please input the keyword and password to replace.</p>
<form action="$script" method="post">
 <div>
  <input type="hidden" name="plugin" value="replace_tak" />
  <p>Page Prefix (option, 'Regular Expression (Perl-Compatible)' needed)<br />
  <input type="text" name="prefix" size="60" value="{$prefix}" /> </p>
  <p>Search word ('Regular Expression (Perl-Compatible)' needed)<br />
  <input type="text" name="search" size="60" value="{$search}" /></p>
  <p>Replace word<br />
  <input type="text" name="replace" size="60" value="{$replace}" /> </p>
  <p>Password<br />
  <input type="password" name="pass" size="12" value="{$pass}" /> </p>
  <p><input type="checkbox" name="preserveTimeStamp" {$statTimeStamp} /> preserve time stamp</p>
  <p><input type="submit" name="act" value="Preview" /> {$replacebutton}</p>
 </div>
</form>

EOD;
	
	if ( $search != '' ){
		$body .= "<p>Target: " . count($changedpages) . " page(s).</p>\n";
	}
	if (count($changedpages)>0){
		$body .= "<ul>\n";
		foreach ($changedpages as $page){
			$body .= '<li>'.make_link($page)."\n";
		}
		$body .= "</ul>\n";
	}
	return array('msg'=>'Replace with regular expression','body'=>$body);
}
?>
