<?php
function ajax_customCategory ($param) {
  global $db;

  if (!$db) {
    return null;
  }

  if (isset($param['list'])) {
    // the popularity column counts every acess with declining value over time,
    // it halves every year.
    $stmt = $db->prepare("select customCategory.id, customCategory.created, customCategory.content, t.accessCount, t.popularity, t.lastAccess from customCategory left join (select id, count(id) accessCount, sum(1/((julianday('2023-08-06 00:00:00') - julianday(ts))/365.25 + 1)) popularity, max(ts) lastAccess from customCategoryAccess group by id) t on customCategory.id=t.id order by popularity desc, created desc limit 25");
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $data = array_map(function ($d) {
      $d['popularity'] = (float)$d['popularity'];
      $d['accessCount'] = (int)$d['accessCount'];

      $content = yaml_parse($d['content']);
      if ($content && is_array($content) && array_key_exists('name', $content)) {
        $d['name'] = lang($content['name']);
      }
      else {
        $d['name'] = 'Custom ' . substr($d['id'], 0, 6);
      }

      unset($d['content']);
      return $d;
    }, $data);

    $stmt->closeCursor();
    return $data;
  }

  if ($param['id']) {
    $stmt = $db->prepare("select content from customCategory where id=:id");
    $stmt->bindValue(':id', $param['id'], PDO::PARAM_STR);
    if ($stmt->execute()) {
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      $result = $row['content'];
      $stmt->closeCursor();

      customCategoryUpdateAccess($param['id']);

      return $result;
    }

    return false;
  }

  if ($param['content']) {
    $id = md5($param['content']);

    $stmt = $db->prepare("insert or ignore into customCategory (id, content) values (:id, :content)");
    $stmt->bindValue(':id', $id, PDO::PARAM_STR);
    $stmt->bindValue(':content', $param['content'], PDO::PARAM_STR);
    $result = $stmt->execute();

    customCategoryUpdateAccess($id);

    return $result;
  }
}

function customCategoryUpdateAccess ($id) {
  global $db;

  if (!isset($_SESSION['customCategoryAccess'])) {
    $_SESSION['customCategoryAccess'] = [];
  }

  // update access per session only once a day
  if (array_key_exists($id, $_SESSION['customCategoryAccess']) && $_SESSION['customCategoryAccess'][$id] > time() - 86400) {
    return;
  }

  $_SESSION['customCategoryAccess'][$id] = time();

  $stmt = $db->prepare("insert into customCategoryAccess (id) values (:id)");
  $stmt->bindValue(':id', $id);
  $stmt->execute();
}
