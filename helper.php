<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Esther Brunner <wikidesign@gmail.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class helper_plugin_editor extends DokuWiki_Plugin {
    
  function getInfo(){
    return array(
      'author' => 'Esther Brunner',
      'email'  => 'wikidesign@gmail.com',
      'date'   => '2006-12-10',
      'name'   => 'Editor Plugin (helper class)',
      'desc'   => 'Returns pages recently edited by a given user',
      'url'    => 'http://www.wikidesign.ch/en/plugin/editor/start',
    );
  }
  
  function getMethods(){
    $result = array();
    $result[] = array(
      'name'   => 'getEditor',
      'desc'   => 'returns  pages recently edited by a given user',
      'params' => array(
        'namespace (optional)' => 'string',
        'number (optional)' => 'integer',
        'user (required)' => 'string'),
      'return' => array('pages' => 'array'),
    );
    return $result;
  }

  /**
   * Get pages edited by user from a given namespace
   */
  function getEditor($ns = '', $num = NULL, $user = ''){
    global $conf;
  
    if (!$user) $user = $_REQUEST['user'];
    
    $first  = $_REQUEST['first'];
    if (!is_numeric($first)) $first = 0;
    
    if ((!$num) || (!is_numeric($num))) $num = $conf['recent'];
    
    $result = array();
    $count  = 0;
      
    // read all recent changes. (kept short)
    $lines = file($conf['changelog']);
  
    // handle lines
    for ($i = count($lines)-1; $i >= 0; $i--){
      $rec = $this->_handleRecent($lines[$i], $ns, $user);
      if($rec !== false) {
        if(--$first >= 0) continue; // skip first entries
        $result[] = $rec;
        $count++;
        // break when we have enough entries
        if($count >= $num){ break; }
      }
    }
              
    return $result;
  }
    
/* ---------- Changelog function adapted for the Editor Plugin ---------- */
    
  /**
   * Internal function used by $this->getPages()
   *
   * don't call directly
   *
   * @see getRecents()
   * @author Andreas Gohr <andi@splitbrain.org>
   * @author Ben Coburn <btcoburn@silicodon.net>
   * @author Esther Brunner <wikidesign@gmail.com>
   */
  function _handleRecent($line, $ns, $user){
    static $seen  = array();         //caches seen pages and skip them
    if(empty($line)) return false;   //skip empty lines
    
    if (preg_match("/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/", $user)) $type = 'ip';
    else $type = 'user';
  
    // split the line into parts
    $recent = parseChangelogLine($line);
    if ($recent===false) { return false; }
  
    // skip seen ones
    if(isset($seen[$recent['id']])) return false;
    
    // entry clauses for user and ip filtering
    switch ($type){
    case 'user':
      if (($recent['user'] != $user) && ($user != '@ALL')) return false;
      break;
    case 'ip':
      if ($recent['ip'] != $user) return false;
      break;
    }
  
    // skip minors
    if ($recent['type']==='e') return false;
  
    // remember in seen to skip additional sights
    $seen[$recent['id']] = 1;
  
    // check if it's a hidden page
    if(isHiddenPage($recent['id'])) return false;
  
    // filter namespace
    if (($ns) && (strpos($recent['id'], $ns.':') !== 0)) return false;
    
    // check ACL
    $recent['perm'] = auth_quickaclcheck($recent['id']);
    if ($recent['perm'] < AUTH_READ) return false;
  
    // check existance
    $recent['file'] = wikiFN($recent['id']);
    $recent['exists'] = @file_exists($recent['file']);
    if (!$recent['exists']) return false;
  
    return $recent;
  }
    
}
  
//Setup VIM: ex: et ts=4 enc=utf-8 :