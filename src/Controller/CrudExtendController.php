<?php 

namespace its4u\lumenAngularCodeGenerator\Controller;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use biliboobrian\MicroServiceCore\Pagination\Paginator;
use biliboobrian\MicroServiceCrud\Http\Controllers\CrudController;
use Illuminate\Support\Facades\Log;

class CrudExtendController extends CrudController
{
    /**
     * Get filtered list of items.
     *
     * @return Response
     */
    public function get(Request $request)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | GET');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | GET');
            }

            Log::channel('crud')->info('    |  > FILTERS   : ' . $request->input('filters'));
            Log::channel('crud')->info('    |  > RELATIONS : ' . $request->input('relations'));
            Log::channel('crud')->info('    |  > SORT      : ' . $request->input('sort'));
        }

        $relations = json_decode($request->input('relations'));

        $tags = null;
        $list = null;

        if ($relations) {
            $list = $this->getRelationsWith($relations);
            $tags = $this->getTagsList($relations);
        }

        $tagList = [$this->modelTableName];

        if ($tags) {
            $tagList = array_merge([$this->modelTableName], $tags);
        }

        // Check the cache for data. Otherwise get from the db.
        if ($list) {
            $query = call_user_func([$this->getModelClass(), 'with'], $list);
        } else {
            $query = call_user_func([$this->getModelClass(), 'query']);
        }

        $filters = json_decode($request->input('filters'));
        $sort = json_decode($request->input('sort'));
        $sortColumn = $request->input('sort-column');
        $sortDirection = $request->input('sort-direction', 'asc'); // default value asc
        $perPage = (int)$request->input('per-page', 10); // default value 10
        $currentPage = (int)$request->input('current-page', 1); // default value 1

        if ($perPage > 100) {
            $perPage = 100;
        }

        if ($filters) {
            $this->createSQLFilter($filters, $query);
        }

        // sort

        if ($sort) {
            $this->createSorting($query, $sort);
        }

        //$page = $query->paginate($perPage);
        $b64 = base64_encode(serialize($request->all()));
        $offset = ($currentPage - 1) * $perPage;

        $page = Cache::tags($tagList)->rememberForever($this->modelTableName . ':search:' . $b64, function () use ($query, $perPage, $offset) {
            return array(
                'count' => $query->count(),
                'result' => $query->skip($offset)->take($perPage)->get([$this->modelTableName .'.*'])
            );
        });

        return $this->generatePaginatedResponse(
            new Paginator(
                $page['count'],
                $perPage,
                $currentPage
            ),
            $this->modelTableName,
            $page['result']->toArray()
        );
    }

    /**
     * Get a single item by it's ID.
     *
     * @param int $id
     * @return Response
     */
    public function getById(Request $request, $id)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | GET BY ID');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | GET BY ID');
            }

            Log::channel('crud')->info('    |  > ID        : ' . $id);
            Log::channel('crud')->info('    |  > RELATIONS : ' . $request->input('relations'));
        }

        $relations = json_decode($request->input('relations'));
        $tags = null;
        $list = null;

        if ($relations) {
            $list = $this->getRelationsWith($relations);
            $tags = $this->getTagsList($relations);
            $b64 = base64_encode(serialize($relations));
        } else {
            $b64 = '';
        }
        $tagList = [$this->modelTableName];

        if ($tags) {
            $tagList = array_merge([$this->modelTableName], $tags);
        }

        // Check the cache for item data. Otherwise get from the db.
        $item = Cache::tags($tagList)->rememberForever($this->modelTableName . ':' . $id . ':' . $b64, function () use ($id, $list, $relations) {
            if ($list) {
                $obj = call_user_func([$this->getModelClass(), 'with'], $list)->findOrFail($id);
                $obj = $this->sortcollections($obj, $relations);
                $returnObj = $this->getSotedRelationship($obj, $relations);
                
                return $returnObj;
            } else {
                return call_user_func([$this->getModelClass(), 'findOrFail'], $id)->toArray();
            }
        });

        return $this->generateResponse($this->modelTableName, $item);
    }

    public function getSotedRelationship($obj, $relations) {
        $returnObj = $obj->toArray();
        
        foreach ($relations as $relation) {
            $relationship = str_replace('-', '_', $relation->table);

            if(isset($obj->$relationship)) {
                $returnObj[$relationship] = $this->getSotedRelationship($obj->$relationship, $relation->relations);
            }
        }

        return $returnObj;
    }

    /**
     * Update the specified item.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | UPDATE');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | UPDATE');
            }

            Log::channel('crud')->info('    |  > ID        : ' . $id);
            Log::channel('crud')->info('    |  > DATA      : ' . $request->all());
        }

        // Get the item.
        $item = call_user_func([$this->getModelClass(), 'findOrFail'], $id);

        // Validate the request.
        $this->validate($request, $item->getValidationRules('update', $id));
        $itemData = $request->all();

        foreach ($itemData as $key => $value) {
            if (array_search($key, $item->getDates()) !== false && is_numeric($value)) {
                $itemData[$key] = Carbon::createFromTimestampMs($value);
            }
        }

        // Update the item.
        $item->fill($itemData);
        $item->save();

        return $this->generateResponse($this->modelTableName, $item->toArray());
    }

    private function sortcollections($obj, array $relations)
    {
        foreach ($relations as $relation) {
            $table = str_replace('-', '_', $relation->table);
            $getTable = lcfirst(str_replace('-', '', ucwords($relation->table, '-')));
            
            if(isset($relation->sortColumn)) {
                if(isset($relation->sortDesc) && $relation->sortDesc === true) {
                    $obj->$table = $obj->$getTable->sortByDesc($relation->sortColumn)->values();
                } else {
                    $obj->$table = $obj->$getTable->sortBy($relation->sortColumn)->values();
                }
            }
            if (property_exists($obj, $table)) { 
                $obj->$table = $this->sortcollections($obj->$getTable, $relation->relations);
            } 
        }

        return $obj;
    }

    private function getTagsList(array $relations)
    {
        $list = array();

        foreach ($relations as $relation) {
            $key = $relation->table;

            if (strpos($key, '.') !== false) {
                $key = substr($key, strpos($key, '.') + 1, strlen($key));
            }

            if (substr($key, -1, 1) === 's') {
                $key = substr(str_replace('-', '_', ($key)), 0, -1);
            } else {
                $key = str_replace('-', '_', ($key));
            }

            if (in_array($key, $list) === false) {
                array_push($list, $key);
            }

            $list = array_merge($list, $this->getTagsList($relation->relations));
        }

        return $list;
    }

    private function getRelationsWith(array $relations, $prefix = '')
    {
        $list = array();

        foreach ($relations as $relation) {
            $table = lcfirst(str_replace('-', '', ucwords($relation->table, '-')));
            if ($prefix !== '') {
                $table = $prefix . '.' . $table;
            }
            if(sizeof($relation->filters) == 0) {
                array_push($list, $table);
            } else {
                $list[$table] = function($query) use ($relation) {
                    foreach ($relation->filters as $filter) {
                        $query->where($filter->column, $filter->operation, $filter->value);
                    }
                };
            }
            

            $list = array_merge($list, $this->getRelationsWith($relation->relations, $table));
        }
        
        return $list;
    }

    private function createSorting($query, $sort) {
        if(property_exists($sort, 'joinTable')) {
            $query->join($sort->joinTable, $this->modelTableName .'.'. $sort->tableKey, '=', $sort->joinTable .'.'. $sort->joinKey);
        } 
        if(property_exists($sort, 'sortColumn')) {
            $dir = "asc";
            if(property_exists($sort, 'sortDirection')) {
                $dir = $sort->sortDirection;
            }
            $query->orderBy($sort->sortColumn, $dir);
        }
    }

    private function createSQLFilter($filters, &$query, $rel = null)
    {

        
        
        if ($filters->relationName) {
            $itemRelation = lcfirst(str_replace('-', '', ucwords($filters->relationName, '-')));

            if($rel) {
                array_push($rel, $itemRelation);
            } else {
                $rel = [$itemRelation];
            }

            $query->whereHas(implode('.', $rel), function ($query) use ($filters) { 
                $this->applyFilterMembers($filters->members, $filters->andLink, $query);
            });
        } else {
            $this->applyFilterMembers($filters->members, $filters->andLink, $query);  
        }

        if ($filters->childrens && sizeOf($filters->childrens) > 0) {
            if ($filters->andLink) {
                $query->where(function ($query) use ($filters, $rel) {
                    foreach ($filters->childrens as $child) {
                        $this->createSQLFilter($child, $query, $rel);
                    }
                });
            } else {
                $query->orWhere(function ($query) use ($filters, $rel) {
                    foreach ($filters->childrens as $child) {
                        $this->createSQLFilter($child, $query, $rel);
                    }
                });
            }
        }
    }

    private function applyFilterMembers($filters, $andLink, &$query) {
        foreach ($filters as $filter) {
            $c = $filter->column;
            if($filter->type === 'date') {
                $c = 'TO_CHAR(' . $filter->column . ', \'YYYY-MM-DD\')';
            } else if($filter->type === 'int') {
                $c = $filter->column . '||\'\'';
            }

            if ($andLink) {
                if(!$filter->field) {
                    if ($filter->operation === 'like') {
                        if(strpos($filter->value, '*') === 0) {
                            $query->whereRaw('lower('. $c.') '. $filter->operation . ' ?', '%' . strtolower(substr($filter->value, 1)) . '%');
                        } else {
                            $query->whereRaw('lower('. $c .') '. $filter->operation . ' ?',  strtolower($filter->value) . '%');
                        }
                        
                    } else if ($filter->operation === 'is null') {
                        $query->whereNull($filter->column);
                    } else if ($filter->operation === 'in') {
                        $query->whereIn($filter->column, explode(',', $filter->value));
                    } else {
                        if($filter->type === 'date') {
                            $query->whereRaw($c .' '. $filter->operation . ' ?', $filter->value);
                        } else {
                            $query->where($c, $filter->operation, $filter->value);
                        }
                    }
                } else {
                    $query->whereColumn($filter->column, $filter->operation, $filter->value);
                }
               
            } else {
                if(!$filter->field) {
                    if ($filter->operation === 'like') {
                        if(strpos($filter->value, '*') === 0) {
                            $query->orWhereRaw('lower('. $c .') '. $filter->operation . ' ?', '%' . strtolower(substr($filter->value, 1)) . '%');
                        } else {
                            $query->orWhereRaw('lower('. $c .') '. $filter->operation . ' ?',  strtolower($filter->value) . '%');
                        }
                    } else if ($filter->operation === 'is null'){
                        $query->orWhereNull($filter->column);
                    } else if ($filter->operation === 'in') {
                        $query->orWhereIn($filter->column, explode(',', $filter->value));
                    } else {
                        if($filter->type === 'date') {
                            $query->orWhereRaw($c .' '. $filter->operation . ' ?', $filter->value);
                        } else {
                            $query->orWhere($c, $filter->operation, $filter->value);
                        }
                    }
                } else {
                    $query->orWhereColumn($filter->column, $filter->operation, $filter->value);
                }
            }
        }
    }

    /**
     * Create a new item.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | STORE');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | STORE');
            }

            Log::channel('crud')->info('    |  > DATA      : ' . $request->all());
            Log::channel('crud')->info('    |  > RELATIONS : ' . $request->input('relations'));
        }

        // Instantiate a new model item.
        $modelClass = $this->getModelClass();
        $item = new $modelClass;

        // Validate the request.
        $this->validate($request, $item->getValidationRules());

        // Create the new item.
        $itemData = $request->all();
        
        foreach ($itemData as $key => $value) {
            if (array_search($key, $item->getDates()) !== false && $value) {
                $itemData[$key] = Carbon::createFromTimestampMs($value);
            }
        }

        $item->fill($itemData);
        $item->save();

        return $this->getById($request, $item->getPrimaryKeyValue());
    }

    /**
     * Create a relation between existing object
     *
     * @param Request $request
     * @return Response
     */
    public function createRelation(Request $request, $id, $relation, $idRelation)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | CREATE RELATION');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | CREATE RELATION');
            }
        
            Log::channel('crud')->info('    |  > ID       : ' . $id);
            Log::channel('crud')->info('    |  > RELATION : ' . $relation);
            Log::channel('crud')->info('    |  > REL ID   : ' . $idRelation);
        }

        $item = call_user_func([$this->getModelClass(), 'findOrFail'], $id);
        $itemRelation = lcfirst(str_replace('-', '', ucwords($relation, '-')));
        $relationQuery = $item->{$itemRelation}();

        $relatedItem = call_user_func([get_class($relationQuery->getRelated()), 'findOrFail'], $idRelation);

        if(method_exists($relationQuery, 'save')) {
            $relationQuery->save($relatedItem);
        } else {
            $relatedItem->save();
            $relationQuery->associate($relatedItem);
            $item->save();
        }

        // Update the item.
        return $this->getById($request, $item->getPrimaryKeyValue());
    }

    /**
     * Delete a relation between existing object
     *
     * @param Request $request
     * @return Response
     */
    public function deleteRelation(Request $request, $id, $relation, $idRelation)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | DELETE RELATION');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | DELETE RELATION');
            }
        
            Log::channel('crud')->info('    |  > ID       : ' . $id);
            Log::channel('crud')->info('    |  > RELATION : ' . $relation);
            Log::channel('crud')->info('    |  > REL ID   : ' . $idRelation);
        }

        $item = call_user_func([$this->getModelClass(), 'findOrFail'], $id);

        $itemRelation = lcfirst(str_replace('-', '', ucwords($relation, '-')));
        $relationQuery = $item->{$itemRelation}();

        $relatedItem = call_user_func([get_class($relationQuery->getRelated()), 'findOrFail'], $idRelation);
        
        
        if(method_exists($relationQuery, 'detach')) {
            $relationQuery->detach($relatedItem);
        } else {
            $relatedItem->delete();
        }

        // Update the item.
        return $this->getById($request, $item->getPrimaryKeyValue());
    }

    /**
     * Delete all records from a relation between existing object
     *
     * @param Request $request
     * @return Response
     */
    public function emptyRelation(Request $request, $id, $relation)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | EMPTY RELATION');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | EMPTY RELATION');
            }
        
            Log::channel('crud')->info('    |  > ID       : ' . $id);
            Log::channel('crud')->info('    |  > RELATION : ' . $relation);
        }
        
        $item = call_user_func([$this->getModelClass(), 'findOrFail'], $id);

        $itemRelation = lcfirst(str_replace('-', '', ucwords($relation, '-')));
        $relationQuery = $item->{$itemRelation}();

        if(method_exists($relationQuery, 'save')) {
            $relationQuery->delete();
        } else {
            $relationQuery->dissociate();
            $item->save();
        }

        // Update the item.
        return $this->getById($request, $item->getPrimaryKeyValue());
    }


    /**
     * Create a relation with a fresh Object
     *
     * @param Request $request
     * @return Response
     */
    public function createRelationWithObject(Request $request, $id, $relation)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | CREATE RELATION WITH OBJECT');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | CREATE RELATION WITH OBJECT');
            }

            Log::channel('crud')->info('    |  > DATA      : ' . $request->all());
            Log::channel('crud')->info('    |  > ID        : ' . $id);
            Log::channel('crud')->info('    |  > RELATION  : ' . $relation);
        }

        $item = call_user_func([$this->getModelClass(), 'findOrFail'], $id);
        $itemRelation = lcfirst(str_replace('-', '', ucwords($relation, '-')));
        $relationQuery = $item->{$itemRelation}();

        // Instantiate a new model item.
        $modelClass = get_class($relationQuery->getRelated());
        $relatedItem = new $modelClass;

        // Validate the request.
        $this->validate($request, $relatedItem->getValidationRules());

        // Create the new item.
        $itemData = $request->all();

        foreach ($itemData as $key => $value) {
            if (array_search($key, $relatedItem->getDates()) !== false) {
                $itemData[$key] = Carbon::createFromTimestampMs($value);
            }
        }

        $relatedItem->fill($itemData);

        if(method_exists($relationQuery, 'save')) {
            $relationQuery->save($relatedItem);
        } else {
            $relatedItem->save();
            $relationQuery->associate($relatedItem);
            $item->save();
        }

        //return $this->getById($request, $relatedItem->getPrimaryKeyValue());

        return $this->generateResponse($relatedItem->getTable(), $relatedItem->toArray());
    }



    /**
     * get all relation linked to model
     *
     * @param Request $request
     * @return Response
     */
    public function getRelation(Request $request, $id, $relation)
    {
        $crudChannel = env('LOG_CRUD_CHANNEL', '');

        if($crudChannel !== '') {
            if($request->user) {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass  . ' | ' . $request->user->username . ' | GET RELATION');
            } else {
                Log::channel('crud')->info($_SERVER['REMOTE_ADDR'] . ' | ' . $this->modelBaseClass . ' | GET RELATION');
            }
    
            Log::channel('crud')->info('    |  > ID        : ' . $id);
            Log::channel('crud')->info('    |  > RELATION  : ' . $relation);
        }
        
        $item = call_user_func([$this->getModelClass(), 'findOrFail'], $id);
        $relation = lcfirst(str_replace('-', '', ucwords($relation, '-')));
        $relations = $item->$relation;
        $relationQuery = $item->{$relation}();

        if($relations) {
            return $this->generateResponse($relationQuery->getRelated()->getTable(), $relations->toArray());
        } else {
            return $this->generateResponse($relationQuery->getRelated()->getTable() , []);
        }
    }
}