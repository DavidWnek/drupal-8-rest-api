<?php

namespace Drupal\rest_api\Controller;

use Drupal\comment\Entity\Comment;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\image\Entity\ImageStyle;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class APIController extends ControllerBase
{
  /**
   * @param string|int $node_id
   * @return JsonResponse
   *
   * Status Results:
   *
   * 0  : Success
   * 10 : No Node
   *
   */
  public function get_node($node_id)
  {
      $node = Node::load($node_id);

      if($node === null) {
          return $this->createErrorResponse(10, 'No Node');
      }

      return new JsonResponse($this->_get_node($node));
  }

    /**
     * @param Node $node
     * @return array
     */
  public function _get_node(Node $node)
  {
      $n = $node->toArray();

      $images = array(
          'field_thumbnail',
          'field_image',
      );

      //Iterates over fields named field_thumbnail and field_image to try and generate a URL for that image.
      foreach($n as $key => $value) {
          if(in_array($key, $images) && count($value) > 0) {
              foreach($value as $k => $v) {
                  $v['url'] = ImageStyle::load('large')->buildUrl($node->$key->entity->getFileUri());
                  $n[$key][$k] = $v;
              }
          }
      }

      //Adds author to the retuning node
      $n['author'] = $node->getOwner()->toArray();

      //Queries for comments left on a certain node.
      $cids = \Drupal::entityQuery('comment')
          ->condition('entity_id', $node->id())
          ->condition('entity_type', 'node')
          ->sort('cid', 'DESC')
          ->execute();

      $comments = [];

      foreach($cids as $cid) {
          $comment = Comment::load($cid);

          $c = $comment->toArray();
          $c['author'] = $comment->getOwner()->toArray();
          $comments[] = $c;
      }

      $n['comments'] = $comments;

    return $n;
  }

  public function get_nodes(Request $request)
  {
      $limit = 100;
      $page = 0;
      $type = null;
      if($request->query->has('limit')) {
          $limit = $request->query->get('limit');
      }

      if($request->query->has('page')) {
          $page = $request->query->get('page');
      }

      if($request->query->has('type')) {
          $type = $request->query->get('type');
      }
      dump($request);
      dump($limit);
      dump($page);
      dump($type);
      $query = \Drupal::entityQuery('node');

      if($type !== null) {
          $query->condition('type', $type);
      }
      $query->lim
      $nids = $query->execute();

      dump($nids);
      exit();
  }

    /**
     * @param string $name
     * @return JsonResponse
     */
  public function get_menu($name)
  {
    $menu_tree = \Drupal::menuTree();
    $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($name);

    $tree = $menu_tree->load($name, $parameters);

    $menu = array();
    /** @var MenuLinkTreeElement $item */
    foreach($tree as $item) {
      $link = $item->link->getPluginDefinition();

      if(substr($link['url'], 0, 12) === 'base:router_') {
          $link['path'] = './'.substr($link['url'], 12);
      } else {
          $link['path'] = './';
      }
      $menu[] = $link;
    }

    return new JsonResponse($menu);
  }

    /**
     * @param $code
     * @param $message
     * @return JsonResponse
     */
  private function createErrorResponse($code, $message)
  {
    return new JsonResponse(array(
      'code' => $code,
      'message' => $message,
      'type' => 'error',
    ));
  }
}