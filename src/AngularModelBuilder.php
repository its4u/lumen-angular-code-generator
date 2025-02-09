<?php

namespace its4u\lumenAngularCodeGenerator;

use Doctrine\DBAL\Schema\Table;
use Illuminate\Database\DatabaseManager;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use its4u\lumenAngularCodeGenerator\Model\HasOne;
use its4u\lumenAngularCodeGenerator\Model\HasMany;
use its4u\lumenAngularCodeGenerator\Model\BelongsTo;
use its4u\lumenAngularCodeGenerator\Model\ClassModel;
use its4u\lumenAngularCodeGenerator\Model\MethodModel;
use its4u\lumenAngularCodeGenerator\Model\AngularModel;
use its4u\lumenAngularCodeGenerator\Model\ArgumentModel;
use its4u\lumenAngularCodeGenerator\Model\BelongsToMany;
use its4u\lumenAngularCodeGenerator\Model\DocBlockModel;
use its4u\lumenAngularCodeGenerator\Model\PropertyModel;
use its4u\lumenAngularCodeGenerator\Model\NamespaceModel;
use its4u\lumenAngularCodeGenerator\Model\ImportClassModel;
use its4u\lumenAngularCodeGenerator\Model\VirtualPropertyModel;
use its4u\lumenAngularCodeGenerator\Exception\GeneratorException;

class AngularModelBuilder
{
    /**
     * @var AbstractSchemaManager
     */
    protected $manager;

    /**
     * @var array
     */
    protected $exportProperties = [];

    /**
     * @var array
     */
    protected $relations = [];

    /**
     * @var array
     */
    protected $dateProperties = [];

    /**
     * @var array
     */
    protected $boolProperties = [];

    /**
     * Builder constructor.
     * @param DatabaseManager $databaseManager
     */
    public function __construct(DatabaseManager $databaseManager)
    {
        try {
            $this->manager = $databaseManager->connection()->getDoctrineSchemaManager();
        } catch (\Exception $e) {   // connection error
            echo $e->getMessage();
            return;
        }
        $dp = $this->manager->getDatabasePlatform();
        $dp->registerDoctrineTypeMapping('enum', 'array');
        $dp->registerDoctrineTypeMapping('set', 'array');
    }

    public function getTableList()
    {
        return $this->manager->listTables();
    }

    /**
     * @param Config $config
     * @return AngularModel
     * @throws GeneratorException
     */
    public function createModel(Config $config)
    {
        $model = new AngularModel(
            $config->get('class_name'),
            $config->get('base_class_angular_model_name'),
            $config->get('base_class_angular_model_from'),
            $config->get('table_name')
        );

        if (!$this->manager->tablesExist($model->getTableName())) {
            throw new GeneratorException(sprintf('Table %s does not exist', $model->getTableName()));
        }

        $this->setNamespace($model, $config)
            ->setCustomProperties($model, $config)
            ->setFields($model)
            ->setRelations($model, $config)
            // ->createGetPatchValue($model, $config)
            ->setConstructor($model, $config);

        return $model;
    }

    protected function exportProp($props) {
        $propArray = [];

        foreach($props as $prop) {
            $propArray[] = 'new CrudColumn(\''. $prop->name .'\', CrudColumn.'. $prop->type .', '. $prop->maxLength .', '. $prop->nullable .')';
        }

        return $propArray;
    }

    /**
     * @param AngularModel $model
     * @param Config $config
     * @return $this
     */
    protected function setConstructor(AngularModel $model, Config $config)
    {
        $tableDetails = $this->manager->listTableDetails($model->getTableName());
        $primaryColumnNames = $tableDetails->getPrimaryKey()->getColumns();

        if (count($primaryColumnNames) > 1) {
            $primaryColumnNames = [$primaryColumnNames[0]];
        }

        $constructBody = 'super(obj, crudService);' . PHP_EOL;
        $constructBody .= '        this.table = \'' . str_replace('_', '-', strtolower($config->get('table_name'))) . 's\';' . PHP_EOL;
        $constructBody .= '        this.primaryKey = \'' . strtolower($primaryColumnNames[0]) . '\';' . PHP_EOL;

        if(sizeof($this->exportProperties) > 0) {
            $constructBody .= '        this.exportProperties = [' . PHP_EOL . '            ' . implode(',' . PHP_EOL . '            ', $this->exportProp($this->exportProperties))  . PHP_EOL . '        ];' . PHP_EOL . PHP_EOL;
        }
        
        if(sizeof($this->relations) > 0) {
            $constructBody .= '        this.relations = [' . PHP_EOL . '            \'' . implode('\',' . PHP_EOL . '            \'', $this->relations) . '\'' . PHP_EOL . '        ];' . PHP_EOL . PHP_EOL;
        }

        if(sizeof($this->dateProperties) > 0) {
            $constructBody .= '        this.datePropeties = [' . PHP_EOL . '            \'' . implode('\',' . PHP_EOL . '            \'', $this->dateProperties) . '\'' . PHP_EOL . '        ];' . PHP_EOL . PHP_EOL;
        }

        if(sizeof($this->boolProperties) > 0) {
            $constructBody .= '        this.boolPropeties = [' . PHP_EOL . '            \'' . implode('\',' . PHP_EOL . '            \'', $this->boolProperties) . '\'' . PHP_EOL . '        ];' . PHP_EOL . PHP_EOL;
        }

        $constructBody .= '        if (obj) {' . PHP_EOL . '            this.importData(obj);' . PHP_EOL . '        }' . PHP_EOL . PHP_EOL;

        $constructMethod = new MethodModel('constructor', '', 'angular');
        $constructMethod->addArgument(new ArgumentModel('obj?', 'Object', null, 'angular'));
        $constructMethod->addArgument(new ArgumentModel('crudService?', 'CrudService', null, 'angular'));
        $constructMethod->setBody($constructBody);
        $model->addMethod($constructMethod, false);

        return $this;
    }

    /**
     * @param AngularModel $model
     * @param Config $config
     * @return $this
     */
    protected function createGetPatchValue(AngularModel $model, Config $config)
    {
        $constructBody = 'return {' . PHP_EOL;

        for ($i = 0; $i < count($this->exportProperties); $i++) {
            $constructBody .= '            ' . $this->exportProperties[$i]->name . ': this.' . $this->exportProperties[$i]->name;

            if ($i === count($this->exportProperties) - 1) {
                $constructBody .= PHP_EOL;
            } else {
                $constructBody .= ',' . PHP_EOL;
            }     
        }

        $constructBody .= '        };' . PHP_EOL;

        $constructMethod = new MethodModel('getPatchValue', '', 'angular');
        $constructMethod->setReturnType('Object');
        $constructMethod->setBody($constructBody);
        $model->addMethod($constructMethod, false);

        return $this;
    }


    /**
     * @param AngularModel $model
     * @param Config $config
     * @return $this
     */
    protected function setNamespace(AngularModel $model, Config $config)
    {
        $namespace = $config->get('lumen_model_namespace');
        $model->setNamespace(new NamespaceModel($namespace));

        return $this;
    }

    /**
     * @param AngularModel $model
     * @param Config $config
     * @return $this
     */
    protected function setCustomProperties(AngularModel $model, Config $config)
    {
        if ($config->get('no_timestamps') == true) {
            $pNoTimestamps = new PropertyModel('timestamps', 'public', false);
            $pNoTimestamps->setDocBlock(
                new DocBlockModel('Indicates if the model should be timestamped.', '', '@var bool')
            );
            $model->addProperty($pNoTimestamps);
        }

        if ($config->has('date_format')) {
            $pDateFormat = new PropertyModel('dateFormat', 'protected', $config->get('date_format'));
            $pDateFormat->setDocBlock(
                new DocBlockModel('The storage format of the model\'s date columns.', '', '@var string')
            );
            $model->addProperty($pDateFormat);
        }

        if ($config->has('connection')) {
            $pConnection = new PropertyModel('connection', 'protected', $config->get('connection'));
            $pConnection->setDocBlock(
                new DocBlockModel('The connection name for the model.', '', '@var string')
            );
            $model->addProperty($pConnection);
        }

        return $this;
    }

    /**
     * @param AngularModel $model
     * @return $this
     */
    protected function setFields(AngularModel $model)
    {
        $tableDetails = $this->manager->listTableDetails($model->getTableName());
        $primaryColumnNames = $tableDetails->getPrimaryKey()->getColumns();

        $hasTimestamps = false;
        $isAutoincrement = true;
        $columnNames = [];
        $dates = [];
        $this->exportProperties = [];
        $this->dateProperties = [];
        $this->boolProperties = [];
        $addMomentImport = false;


        foreach ($tableDetails->getColumns() as $column) {

            $colName = strtolower($column->getName());

            /*if (in_array($colName, ['created_at', 'updated_at'])) {
                $hasTimestamps = true;
                continue;   // remove timestamps
            }*/
            
            $model->addProperty(new PropertyModel(
                '_' . $colName,
                'private',
                $column->getComment(),
                'angular',
                $this->resolveType($column->getType()->getName())

            ));

            $prop = new \stdClass;
            $prop->name = $colName;
            $prop->type = $this->resolveTypeForm($this->resolveType($column->getType()->getName()));
            $prop->maxLength = ($column->getLength())?$column->getLength():0;
            $prop->nullable = ($column->getNotnull())?'false':'true';

            $this->exportProperties[] = $prop;

            if($column->getType()->getName() === 'date'
            || $column->getType()->getName() === 'datetime') {
                $this->dateProperties[] = $colName;
            }

            if($column->getType()->getName() === 'boolean') {
                $this->boolProperties[] = $colName;
            }

            if (in_array($colName, $primaryColumnNames)) {
                $isAutoincrement = $column->getAutoincrement();
            }
           
            //if (!in_array($colName, $primaryColumnNames)) {
            $columnNames[] = $colName;
            //}

            $getMethod = new MethodModel('get ' . $colName, 'public', 'angular');
            $getMethod->setBody('return this._' . $colName . ';');

            
            switch($column->getType()->getName()) {
                case 'array':
                $getMethod->setBody('try {' . PHP_EOL . '            return JSON.parse(this._' . $colName . ');' . PHP_EOL . '         } catch (e) {' . PHP_EOL . '            return null;' . PHP_EOL . '        }');

                break;
                default:
                $getMethod->setBody('return this._' . $colName . ';');

            }

            $model->addMethod($getMethod);

            $setMethod = new MethodModel('set ' . $colName, 'public', 'angular');
            switch($column->getType()->getName()) {
                case 'datetime':
                $setMethod->addArgument(new ArgumentModel('val', $this->resolveType($column->getType()->getName()), null, 'angular'));
                $setMethod->setBody('if (val !== this._' . $colName . ') {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            if (val) {' . PHP_EOL . '                this._' . $colName . ' = moment(val);' . PHP_EOL . '            } else {' . PHP_EOL . '                this._' . $colName . ' = null;' . PHP_EOL . '            }' . PHP_EOL . '        }');
                $addMomentImport = true;
                
                break;
                case 'date':
                $setMethod->addArgument(new ArgumentModel('val', $this->resolveType($column->getType()->getName()), null, 'angular'));
                $setMethod->setBody('if (val !== this._' . $colName . ') {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            if (val) {' . PHP_EOL . '                this._' . $colName . ' = moment(val);' . PHP_EOL . '            } else {' . PHP_EOL . '                this._' . $colName . ' = null;' . PHP_EOL . '            }' . PHP_EOL . '        }');
                $addMomentImport = true;
                
                break;
                case 'array':
                $setMethod->addArgument(new ArgumentModel('val', 'object', null, 'angular'));
                $setMethod->setBody('if (!this._' . $colName . ' || this._' . $colName . ' === \'\' || val !== JSON.parse(this._' . $colName . ')) {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            this._' . $colName . ' = JSON.stringify(val);' . PHP_EOL . '        }');

                break;
                default:
                $setMethod->addArgument(new ArgumentModel('val', $this->resolveType($column->getType()->getName()), null, 'angular'));
                $setMethod->setBody('if (val !== this._' . $colName . ') {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            this._' . $colName . ' = val;' . PHP_EOL . '        }');

            }
            
            $model->addMethod($setMethod);
        }

        if($addMomentImport) {
            $model->addImport(new ImportClassModel('*', 'moment'));
        }

        /* if (!empty($dates)) {
            $datesProperty = new PropertyModel('dates');
            $datesProperty->setAccess('protected')
                ->setValue($dates)
                ->setDocBlock(new DocBlockModel('@var array'));
            $model->addProperty($datesProperty);
        } 

        if (!empty($primaryColumnNames)) {
            $comments = [];
            if (count($primaryColumnNames) > 1) {
                $comments[] = 'Eloquent doesn\'t support composite primary keys : ' . implode(', ', $primaryColumnNames);
                $comments[] = '';
                $primaryColumnNames = [$primaryColumnNames[0]];
            }
            if ($primaryColumnNames[0] != 'id') {
                $comments[] = '@var string';
                $primatyProperty = new PropertyModel('primaryKey');
                $primatyProperty->setAccess('protected')
                    ->setValue($primaryColumnNames[0])
                    ->setDocBlock((new DocBlockModel())->addContent($comments));
                $model->addProperty($primatyProperty);
            }

            $comments = [];
            if (!$isAutoincrement) {
                $comments[] = ['Indicates if the IDs are auto-incrementing.', '', '@var bool'];
                $autoincrementProperty = new PropertyModel('incrementing');
                $autoincrementProperty->setAccess('public')
                    ->setValue(false)
                    ->setDocBlock((new DocBlockModel())->addContent($comments));
                $model->addProperty($autoincrementProperty);
            }

            $comments = [];
            if (!$hasTimestamps) {
                $comments[] = ['Indicates if the model should be timestamped.', '', '@var bool'];
                $timestampsProperty = new PropertyModel('timestamps');
                $timestampsProperty->setAccess('public')
                    ->setValue(false)
                    ->setDocBlock((new DocBlockModel())->addContent($comments));
                $model->addProperty($timestampsProperty);
            }
        } */

        return $this;
    }

    /**
     * @param AngularModel $model
     * @return $this
     */
    protected function setRelations(AngularModel $model, $config)
    {
        $this->relations = [];

        $foreignKeys = $this->manager->listTableForeignKeys($model->getTableName());
        foreach ($foreignKeys as $tableForeignKey) {
            $tableForeignColumns = $tableForeignKey->getForeignColumns();
            $tableName = strtolower($tableForeignKey->getForeignTableName());
            if (count($tableForeignColumns) !== 1) {
                continue;
            }

            $localColumns = $tableForeignKey->getLocalColumns();
            if (count($localColumns) !== 1) {
                continue;
            }
            $tableCC =str_replace('_', '', ucwords(ucfirst(strtolower($tableName)), "_"));

            if($tableName !== $model->getTableName()) {
                $model->addImport(new ImportClassModel($tableCC, './' .str_replace('_', '-', strtolower($tableName))));
            }

            $model->addProperty(new PropertyModel(
                strtolower('_' . $tableName),
                'private',
                null,
                'angular',
                $tableCC

            ));

            $getMethod = new MethodModel('get ' . strtolower($tableName), 'public', 'angular');
            $getMethod->setBody('return this._' . strtolower($tableName) . ';');
            $model->addMethod($getMethod);

            $setMethod = new MethodModel('set ' . strtolower($tableName), 'public', 'angular');
            $setMethod->addArgument(new ArgumentModel('val', $tableCC, null, 'angular'));
            $setMethod->setBody('if (val !== this._' . strtolower($tableName) . ') {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            this._' . strtolower($localColumns[0]) . ' = (val) ? val.' . strtolower($tableForeignColumns[0]) .' : null;' . PHP_EOL . '            this._' . $tableName . ' = val;' . PHP_EOL . '        }');

            $model->addMethod($setMethod);
           //  $model->addRelation($relation);
        }

        $tables = $this->manager->listTables();

        foreach ($tables as $table) {
            if ($table->getName() === $model->getTableName()) {
                continue;
            }
            $foreignKeys = $table->getForeignKeys();
            //echo 'table:' . $table->getName() . PHP_EOL;
            foreach ($foreignKeys as $name => $foreignKey) {
                //echo 'key:' .$foreignKey->getForeignTableName() . PHP_EOL;
                if ($foreignKey->getForeignTableName() === $model->getTableName()) {
                    $localColumns = $foreignKey->getLocalColumns();
                    if (count($localColumns) !== 1) {
                        continue;
                    }

                    if (count($foreignKeys) === 2 && count($table->getColumns()) === 2) {
                        $keys = array_keys($foreignKeys);
                        $key = array_search($name, $keys) === 0 ? 1 : 0;
                        $secondForeignKey = $foreignKeys[$keys[$key]];
                        $tableName = $secondForeignKey->getForeignTableName();
                        $tableCC = str_replace('_', '', ucwords(ucfirst(strtolower($tableName)), "_"));

                        $model->addProperty(new PropertyModel(
                            '_' . strtolower($tableName) . 's',
                            'private',
                            null,
                            'angular',
                            'Array<' . $tableCC .'>'
            
                        ));

                        if($tableName !== $model->getTableName()) {
                            $model->addImport(new ImportClassModel($tableCC, './' .str_replace('_', '-', strtolower($tableName))));
                        }

                        $getMethod = new MethodModel('get ' . strtolower($tableName) . 's', 'public', 'angular');
                        $getMethod->setBody('return this._' . strtolower($tableName) . 's;');
            
                        $model->addMethod($getMethod);
            
                        $setMethod = new MethodModel('set ' . strtolower($tableName) . 's', 'public', 'angular');
                        $setMethod->addArgument(new ArgumentModel('val', 'Array<' . $tableCC .'>', null, 'angular'));
                        $setMethod->setBody('if (val !== this._' . strtolower($tableName) . 's) {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            this._' . strtolower($tableName) . 's = val;' . PHP_EOL . '        }');
            
                        $model->addMethod($setMethod);

                        $this->relations[] = strtolower($tableName . 's');
                        break;
                    } else {
                        $tableName = $foreignKey->getLocalTableName();
                        $foreignColumn = $localColumns[0];
                        $localColumn = $foreignKey->getForeignColumns()[0];
                        $tableCC = str_replace('_', '', ucwords(ucfirst(strtolower($tableName)), "_"));

                        $model->addProperty(new PropertyModel(
                            '_' . strtolower($tableName) . 's',
                            'private',
                            null,
                            'angular',
                            'Array<' . $tableCC .'>'
            
                        ));

                        if($tableName !== $model->getTableName()) {
                            $model->addImport(new ImportClassModel($tableCC, './' .str_replace('_', '-', strtolower($tableName))));
                        }
                        $getMethod = new MethodModel('get ' . strtolower($tableName) . 's', 'public', 'angular');
                        $getMethod->setBody('return this._' . strtolower($tableName) . 's;');
            
                        $model->addMethod($getMethod);
            
                        $setMethod = new MethodModel('set ' . strtolower($tableName) . 's', 'public', 'angular');
                        $setMethod->addArgument(new ArgumentModel('val', 'Array<' . $tableCC .'>', null, 'angular'));
                        $setMethod->setBody('if (val !== this._' . strtolower($tableName) . 's) {' . PHP_EOL . '            this.sync = false;' . PHP_EOL . '            this._' . strtolower($tableName) . 's = val;' . PHP_EOL . '        }');
            
                        $model->addMethod($setMethod);

                        $this->relations[] = strtolower($tableName . 's');
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @param Table $table
     * @param string $column
     * @return bool
     */
    protected function isColumnUnique(Table $table, $column)
    {
        foreach ($table->getIndexes() as $index) {
            $indexColumns = $index->getColumns();
            if (count($indexColumns) !== 1) {
                continue;
            }
            $indexColumn = $indexColumns[0];
            if ($indexColumn === $column && $index->isUnique()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function resolveType($type)
    {
        static $typesMap = [
            'date' => 'any',
            'character varying' => 'string',
            'boolean' => 'boolean',
            'name' => 'string',
            'double precision' => 'number',
            'float' => 'number',
            'integer' => 'number',
            'ARRAY' => 'Array<any>',
            'json' => 'object',
            'timestamp without time zone' => 'string',
            'timestamp' => 'any',
            'text' => 'string',
            'bigint' => 'number',
            'string' => 'string',
            'decimal' => 'number',
            'datetime' => 'any',
            'array' => 'string',   // todo test
        ];

        return array_key_exists($type, $typesMap) ? $typesMap[$type] : '__' . $type;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    protected function resolveTypeForm($type)
    {
        static $typesMap = [
            'any' => 'DATE',
            'string' => 'STRING',
            'boolean' => 'BOOL',
            'number' => 'NUMBER',
            'Array<any>' => 'OBJECT',
            'object' => 'OBJECT'
        ];

        return array_key_exists($type, $typesMap) ? $typesMap[$type] : '__' . $type;
    }
}
