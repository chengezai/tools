<?php
/**
 * Created by PhpStorm.
 * author: _Dust_
 * Date: 2020-06-22
 * Time: 17:20
 */

namespace Chengezai\PTools;

use Illuminate\Support\Facades\DB;
trait BaseTrait
{
    /**
     * 创建错误信息
     * @var string
     */
    static protected $error = '';

    /**
     * 获取错误信息
     * @return string
     */
    static public function getErr()
    {
        return static::$error;
    }

    /**
     * 设置错误信息内容
     * @param $msg
     * @return mixed
     */
    static protected function setErr($msg)
    {
        return static::$error = $msg;
    }

    /**
     * 批量更新1
     * @param array $multipleData
     * @return bool|int
     */
    public function updateBatch($multipleData = [])
    {
        try {
            if (empty($multipleData)) {
                throw new \Exception("数据不能为空");
            }
            $tableName = DB::getTablePrefix() . $this->getTable(); // 表名
            $firstRow  = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
            unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            $sets      = [];
            $bindings  = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = "`" . $uColumn . "` = CASE ";
                foreach ($multipleData as $data) {
                    $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                    $bindings[] = $data[$referenceColumn];
                    $bindings[] = $data[$uColumn];
                }
                $setSql .= "ELSE `" . $uColumn . "` END ";
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings  = array_merge($bindings, $whereIn);
            $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
            // 传入预处理sql语句和对应绑定数据
            return DB::update($updateSql, $bindings);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 拼接批量更新的sql语句
     * 需要配合update更新,以及条件更新,
     * @array $result 数据更新数组
     * @string $whenField 本次更新数据中不重复的数据库字段
     * @return array
     */
    public static function batchUpdate(array $result = [], $whenField = 'id')
    {
        $when = [];
        $update = collect($result);
        foreach ($update->all() as $sets) {
            $whenValue = $sets[$whenField];
            foreach ($sets as $fieldName => $value) {
                if ($fieldName == $whenField) {
                    continue;
                }
                if (is_null($value)) {
                    $value = '';
                }
                $when[$fieldName][] =
                    "when {$whenField} = '{$whenValue}' then " . $value;
            }
        }
        foreach ($when as $fieldName => &$item) {
            $item = DB::raw("case " . implode(' ', $item) . ' end ');
        }
        return $when;
    }

    /**
     * 条件查询
     * @param array $param
     * @return array
     */
    static public function listByWhere($param = [])
    {
        //条件初始化
        $where = isset($param['where']) ? $param['where'] : [];
        $whereIn = isset($param['whereIn']) ? $param['whereIn'] : [];
        $whereOther = isset($param['whereOther']) ? $param['whereOther'] : [];
        $whereMany = isset($param['whereMany']) ? $param['whereMany'] : [];
        $orderBy = isset($param['orderBy']) ? $param['orderBy'] : '';
        $groupBy = isset($param['groupBy']) ? $param['groupBy'] : [];
        $field = isset($param['field']) ? $param['field'] : ['*'];
        $with = isset($param['with']) ? $param['with'] : [];
        $whereHas = isset($param['whereHas']) ? $param['whereHas'] : [];
        $whereBetween = isset($param['whereBetween']) ? $param['whereBetween'] : [];
        array_key_exists('pageSize', $param) OR $param['pageSize'] = 15;
        //开始查询
        $res = self::select($field)
            ->where($where);
        //多个where数组
        if (!empty($whereMany)){
            foreach ($whereMany as $e){
                $res = $res->where($e);
            }
        }
        //模糊/大于/小于/不等于查询
        if (!empty($whereOther)){
            foreach ($whereOther as $e){
                $res = $res->where($e[0],$e[1],$e[2]);
            }
        }

        if (!empty($whereIn)){
            foreach ($whereIn as $e){
                $res = $res->whereIn($e[0],$e[1]);
            }
        }
        //区间条件
            $res = $res->when($whereBetween, function ($query) use ($whereBetween) {
                $query->whereBetween($whereBetween[0],$whereBetween[1]);
            })
            //关联查询
            ->when($with, function ($query) use ($with) {
                foreach ($with as $k => $v) {
                    $query->with($v);
                }
            })
            //连表查询
            ->when($whereHas, function ($query) use ($whereHas) {
                foreach ($whereHas as $k => $v) {
                    $query->whereHas($v[0], $v[1]);
                }
            })
            //排序
            ->when($orderBy, function ($query) use ($orderBy) {
                foreach ($orderBy as $k => $v) {
                    $demo = explode(' ', $v);
                    $query->orderBy($demo[0], $demo[1]);
                }
            })
            //分组
            ->when($groupBy, function ($query) use ($groupBy) {
                $query->groupBy($groupBy);
            })
            ->paginate($param['pageSize'])
            ->toArray();
        return $res;
    }

    /**
     * 条件查询sql对象
     * @param array $param
     * @return array
     */
    static public function listByWhereObj($param = [])
    {
        //条件初始化
        $where = isset($param['where']) ? $param['where'] : [];
        $whereOther = isset($param['whereOther']) ? $param['whereOther'] : [];
        $whereMany = isset($param['whereMany']) ? $param['whereMany'] : [];
        $whereIn = isset($param['whereIn']) ? $param['whereIn'] : [];
        $orderBy = isset($param['orderBy']) ? $param['orderBy'] : '';
        $groupBy = isset($param['groupBy']) ? $param['groupBy'] : [];
        $field = isset($param['field']) ? $param['field'] : ['*'];
        $with = isset($param['with']) ? $param['with'] : [];
        $withCount = isset($param['withCount']) ? $param['withCount'] : [];
        $whereHas = isset($param['whereHas']) ? $param['whereHas'] : [];
        $whereBetween = isset($param['whereBetween']) ? $param['whereBetween'] : [];
        $whereDate = isset($param['whereDate']) ? $param['whereDate'] : [];

        //开始查询
        $res = self::select($field)
            ->where($where);
        //多个where数组
        if (!empty($whereMany)) {
            foreach ($whereMany as $e) {
                $res = $res->where($e);
            }
        }
        //某字段属于多个数组
        if (!empty($whereIn)) {
            foreach ($whereIn as $e) {
                $res = $res->whereIn($e[0], $e[1]);
            }
        }
        //模糊/大于/小于/不等于查询
        if (!empty($whereOther)) {
            foreach ($whereOther as $e) {
                $res = $res->where($e[0], $e[1], $e[2]);
            }
        }
        //时间比较

        if (!empty($whereDate)) {
            foreach ($whereDate as $e) {
                $res = $res->whereDate($e[0],$e[1]);
            }
        }

        //区间条件
        $res = $res->when($whereBetween, function ($query) use ($whereBetween) {
            $query->whereBetween($whereBetween[0],$whereBetween[1]);
        })
            ->when($with, function ($query) use ($with) {
                foreach ($with as $k => $v) {
                    $query->with($v);
                }
            })
            //关联统计
            ->when($withCount, function ($query) use ($withCount) {
                foreach ($withCount as $k => $v) {
                    $query->withCount($v);
                }
            })
            //连表查询
            ->when($whereHas, function ($query) use ($whereHas) {
                foreach ($whereHas as $k => $v) {
                    $query->whereHas($v[0], $v[1]);
                }
            })
            //排序
            ->when($orderBy, function ($query) use ($orderBy) {
                foreach ($orderBy as $k => $v) {
                    $demo = explode(' ', $v);
                    $query->orderBy($demo[0], $demo[1]);
                }
            })
            //分组
            ->when($groupBy, function ($query) use ($groupBy) {
                $query->groupBy($groupBy);
            });

        return $res;
    }

    /**
     * 查询所有
     * @param array $param
     * @return mixed
     */
    static public function getAll($param = [])
    {
        //条件初始化
        $where = isset($param['where']) ? $param['where'] : [];
        $whereIn = isset($param['whereIn']) ? $param['whereIn'] : [];
        $orderBy = isset($param['orderBy']) ? $param['orderBy'] : '';
        $groupBy = isset($param['groupBy']) ? $param['groupBy'] : [];
        $field = isset($param['field']) ? $param['field'] : ['*'];
        $with = isset($param['with']) ? $param['with'] : [];
        $whereHas = isset($param['whereHas']) ? $param['whereHas'] : [];
        $whereBetween = isset($param['whereBetween']) ? $param['whereBetween'] : [];
        array_key_exists('pageSize', $param) OR $param['pageSize'] = config('admin.pageSize');
        //开始查询
        $res = self::select($field)
            ->where($where)
             //关联查询
            ->when($whereIn, function ($query) use ($whereIn) {
                 foreach ($whereIn as $e){
                     $query->whereIn($e[0],$e[1]);
                 }
            })
            //关联查询
            ->when($with, function ($query) use ($with) {
                foreach ($with as $k => $v) {
                    $query->with($v);
                }
            })
            //连表查询
            ->when($whereHas, function ($query) use ($whereHas) {
                foreach ($whereHas as $k => $v) {
                    $query->whereHas($v[0], $v[1]);
                }
            })
            //区间条件
            ->when($whereBetween, function ($query) use ($whereBetween) {
                $query->whereBetween($whereBetween[0],$whereBetween[1]);
            })

            //排序
            ->when($orderBy, function ($query) use ($orderBy) {
                foreach ($orderBy as $k => $v) {
                    $demo = explode(' ', $v);
                    $query->orderBy($demo[0], $demo[1]);
                }
            })
            //分组
            ->when($groupBy, function ($query) use ($groupBy) {
                $query->groupBy($groupBy);
            })
            ->get()
            ->toArray();
        return $res;
    }

    /**
     * 查询单条详情
     * @param $param
     * @return mixed
     */
    static public function getOne($param)
    {
        /*条件初始化*/
        $where = isset($param['where']) ? $param['where'] : [];
        $field = isset($param['field']) ? $param['field'] : ['*'];
        $with = isset($param['with']) ? $param['with'] : [];
        array_key_exists('pageSize', $param) OR $param['pageSize'] = config('admin.pageSize');
        /*开始查询*/
        $res = self::select($field)
            ->where($where)
            /*关联查询*/
            ->when($with, function ($query) use ($with) {
                foreach ($with as $k => $v) {
                    $query->with($v);
                }
            })
            ->first();
        return $res;
    }

    /**
     * 插入
     * @param $param
     * @return array|bool
     */
    static public function add($param)
    {
        try {

            $id = self::insertGetId($param);
            return ['id' => $id];
        } catch (\Exception  $e) {
            self::setErr($e->getMessage());
            return false;
        }
    }

    /**
     * 修改
     * @param $param
     * @return bool|string
     */
    static public function edit($param)
    {
        try {
            self::where('id', $param['id'])->update($param);
            return "ok";
        } catch (\Exception  $e) {
            self::setErr($e->getMessage());
            return false;
        }
    }

    /**
     * 删除
     * @param $id
     * @param string $img
     * @return bool|string
     */
    static public function del($id, $img = '')
    {
        try {
            $row = self::where('id', $id)->first();
            if (empty($row)) throw new \Exception("数据丢失");
            //img表示图片字段名字，如果存在判断该图片是否有值，如果有则删除
            if ((isset($row->$img) && !empty($row->$img))) {
                @unlink('.' . $row->$img);
            }
            $row->delete();
            return "ok";
        } catch (\Exception $e) {
            self::setErr($e->getMessage());
            return false;
        }
    }

    /**
     * 批量添加数据
     * @param array $data
     * @return mixed
     */
    static public function addAll(Array $data)
    {
        $rs = self::insert($data);
        return $rs;
    }

    /**
     * 获得当前id下的所有子级Id
     * @param $id
     * @return array
     */
    static public function getChildren($id)
    {
        $data = self::get()->toArray();
        return self::_children($data, $id);
    }

    /**
     * 获得当前id下的所有子级
     * @param int $id
     * @return array
     */
    static public function getChildrenTree($id = 0)
    {
        $data = self::get()->toArray();
        return self::getChild($data, $id);
    }


    /**
     * 排序加分页的树形结构
     * @param array $param
     * @return array
     */
    static public function getTreeList($param = [])
    {
        $where = isset($param['where']) ? $param['where'] : [];
        $field = isset($param['field']) ? $param['field'] : '*';
        array_key_exists('pageSize', $param) OR $param['pageSize'] = config('admin.pageSize');
        if (!array_key_exists('page', $param) && !array_key_exists('pageSize', $param)) {
            $param['pageSize'] =  99999;
        }
        array_key_exists('pageSize', $param) OR $param['pageSize'] = config('admin.pageSize');
        $res = self::select(DB::raw($field . ',concat(path,"-",id) AS paths'))
            ->orderBy('paths', 'asc')
            ->where($where)
            ->paginate($param['pageSize'])
            ->toArray();
        array_key_exists('p_id', $param) OR $param['p_id'] = 0;
        $res['data'] = _tree($res['data'], $param['p_id']);
        foreach ($res['data'] as &$v) {
            $v['name'] = str_repeat('&nbsp;&nbsp;&nbsp;┗━', $v['level']) . $v['name'];
        }
        unset($v);
        return ['total' => $res['total'], 'list' => $res['data']];
    }

    /**
     * 批量修改数据
     * @param string $tableName
     * @param array $multipleData
     * @return bool|int
     */
    static public function updateAll($tableName = '', $multipleData = [])
    {
        if (empty($tableName)) {
            self::setErr('没有表名');
            return false;
        }
        $tableName = config('database.connections.mysql.prefix') . $tableName;
        if ($tableName && !empty($multipleData)) {

            //所有的字段名
            $updateColumn = array_keys($multipleData[0]);

            //字段的主键id
            $referenceColumn = $updateColumn[0]; //e.g id
            unset($updateColumn[0]);

            //whereIn条件 id的集合
            $whereIn = "";

            $q = "UPDATE " . $tableName . " SET ";
            foreach ($updateColumn as $uColumn) {
                $q .= "`" . $uColumn . "`" . " = CASE ";

                foreach ($multipleData as $data) {
                    //$referenceColumn为主键id，后为主键id的值，$uColumn为更改的字段名称，后为字段的更改值
                    $q .= "WHEN " . "`" . $referenceColumn . "`" . " = " . $data[$referenceColumn] . " THEN '" . $data[$uColumn] . "' ";
                }
                $q .= "ELSE " . "`" . $uColumn . "`" . " END, ";
            }
            foreach ($multipleData as $data) {
                $whereIn .= "'" . $data[$referenceColumn] . "', ";
            }
            $q = rtrim($q, ", ") . " WHERE " . "`" . $referenceColumn . "`" . " IN (" . rtrim($whereIn, ', ') . ")";

            // Update
            return DB::update(DB::raw($q));

        } else {
            return false;
        }

    }


    /**
     * 获取子孙节点
     * @param $param
     * @param int $p_id
     * @param string $text
     * @return array
     */
    static public function getChild($param, $p_id = 0, $text = 'category_name')
    {
        $data = [];
        foreach ($param as $k => $v) {
            if ($v['p_id'] == $p_id) {
                $children = self::getChild($param, $v['id'], $text);
                if (count($children) > 0) {
                    $v['children'] = $children;
                }
                $data[] = $v;
            }
        }
        return $data;
    }

    /**
     * 递归找寻子级id
     * @param $data
     * @param $parent_id
     * @param bool $isClear
     * @return array
     */
    static public function _children($data, $parent_id, $isClear = true)
    {
        static $res = [];
        if ($isClear) $res = [];
        foreach ($data as $k => $v) {
            if ($v['p_id'] == $parent_id) {
                $res[] = $v['id'];
                self::_children($data, $v['id'], false);
            }
        }
        return $res;
    }


    /**
     * 生成带有层级的树形结构
     * @param $data
     * @param $parent_id
     * @param int $level
     * @param bool $isClear
     * @return array
     */
    static public function _tree($data, $parent_id, $level = 0, $isClear = true)
    {
        static $res = [];
        if ($isClear) $res = [];
        foreach ($data as $k => $v) {
            if ($v['p_id'] == $parent_id) {
                $v['level'] = $level;
                $res[] = $v;
                self::_tree($data, $v['id'], $level + 1, false);
            }
        }
        return $res;
    }



}