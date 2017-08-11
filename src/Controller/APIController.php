<?php

namespace Drupal\rest_api\Controller;

use Drupal\comment\Entity\Comment;
use Drupal\Core\Controller\ControllerBase;
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

        if ($node === null) {
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
        foreach ($n as $key => $value) {
            if (in_array($key, $images) && count($value) > 0) {
                foreach ($value as $k => $v) {
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

        foreach ($cids as $cid) {
            $comment = Comment::load($cid);

            $c = $comment->toArray();
            $c['author'] = $comment->getOwner()->toArray();
            $comments[] = $c;
        }

        $n['comments'] = $comments;

        return $n;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     *
     * Returns all nodes.
     *
     * Filters:
     * limit: integer (default: 25)
     * page: integer (default: 0)
     * type: string (default: null)
     *
     * Supports pagination be using the limit and page query variables.
     * Returns next and previous links in results for easy of loading large data.
     *
     * Supports entity type filtering using the type query variable.
     * Filters based on the machine name of the Content Type.
     *
     */
    public function get_nodes(Request $request)
    {
        $limit = 25;
        $page = 0;
        $type = null;

        //Sets variable from url
        if ($request->query->has('limit')) {
            $limit = $request->query->get('limit');
        }

        //Sets variable from url
        if ($request->query->has('page')) {
            $page = $request->query->get('page');
        }

        //Sets variable from url
        if ($request->query->has('type')) {
            $type = $request->query->get('type');
        }

        //Starts building a query for a node
        $query = \Drupal::entityQuery('node');

        //Sets filters for node of certain type
        if ($type !== null) {
            $query->condition('type', $type);
        }

        $results = array(
            'results' => array(),
        );

        //Clones the query to return a total result count.
        $countQuery = clone $query;
        $countQuery->count();
        $results['total_results'] = $countQuery->execute();

        //Sets filter of results for pagination
        $query->range($page * $limit, $limit);

        //returns array of node id's in query.
        $nids = $query->execute();

        //Parses each node, and stores then in array for json response.
        foreach ($nids as $nid) {
            $results['results'][] = $this->_get_node(Node::load($nid));
        }

        //Count of results in this paginated query
        $results['results_count'] = count($nids);

        $query = array(
            'limit' => $limit,
            'type' => $type,
        );

        //Builds previous link.
        if ($page > 0) {
            $results['previous_link'] = '/api/node?'.http_build_query(array_merge(array('page' => $page - 1), $query));
        }

        //Builds next link.
        if ($results['results_count'] + ($page * $limit) <$results['total_results']) {
            $results['next_link'] = '/api/node?'.http_build_query(array_merge(array('page' => $page + 1), $query));
        }

        //Returns results array as json.
        return new JsonResponse($results);
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
        foreach ($tree as $item) {
            $link = $item->link->getPluginDefinition();

            if (substr($link['url'], 0, 12) === 'base:router_') {
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
