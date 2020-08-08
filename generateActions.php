<?php
require_once 'init.php';
$directoryName = 'Action';
$appName ='AppName';
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
ini_set('max_execution_time', 0);

if($_SERVER['REQUEST_METHOD'] == 'POST'){
	$databaseRepository = new EntityGenerator\Database\DatabaseRepository($connection);
	$database 	= filter_input(INPUT_POST,'database');
	$tables 	= explode(',',filter_input(INPUT_POST,'tables'));
	if(empty($databases) == true && isset($tables[0]) && $tables[0] == ''){
		header("Location:index.php?error=Select database and tables");
		return false;
	}
	$connection->exec("USE $database");

	foreach($tables as $table){
		$coloumnNames = $databaseRepository->getColoumnNamesOfTable($table);
		generateActionClass($table, $coloumnNames, $database);
	}
	
	foreach($tables as $table){
		$coloumnNames = $databaseRepository->getColoumnNamesOfTable($table);	
		generateCollectionClass($table,$coloumnNames, $database);
		generateEntityClass($table,$coloumnNames, $database);
	}
	
	header("Location:index.php?success=Successfully Generated Entities");
}else{
	header("Location:index.php?error=Oops! Error in generating entities.");
}

function generateActionClass($tableName, $coloumnNames, $database){
	global $directoryName;
	global $appName;
	
	$actionList=array('create','update','delete');
	
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName);
	
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] == 'PRI' || $coloumnName['Key'] == 'PRIMARY'){
			$primaryKey= EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']);
			$primaryKeyType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		}
	}
	
	foreach($actionList as $anAction){
		$actionClass="";
		$studlyActionName = EntityGenerator\Helper\HelperFunctions::studlyCaps($anAction);
		$actionClass = 
"
<?php 
namespace App\\{$directoryName}\\{$appName}\\{$studlyTableName};

use App\\Service\\{$appName}\\{$studlyTableName};
use Fusio\\Engine\\ActionAbstract;
use Fusio\\Engine\\ContextInterface;
use Fusio\\Engine\\ParametersInterface;
use Fusio\\Engine\\RequestInterface;
use PSX\\Http\\Exception\\InternalServerErrorException;
use PSX\\Http\\Exception\\StatusCodeException;

/**
 * Action which {$anAction} a {$camelTableName}. 
 */
class {$studlyActionName} extends ActionAbstract {
	
";

		//**** TO DO
		// generate vars
		// generate constructor
		$actionClass .= genConstructor($tableName);
		// generate create function
		$actionClass .= genHandleFunc($tableName,$coloumnNames,$primaryKey,$primaryKeyType,$anAction);
		
		$actionClass .= "}";
		writeFile($database,$studlyActionName.'.php', $actionClass, $studlyTableName);
		$actionClass="";
	}
}


function generateCollectionClass($tableName, $coloumnNames, $database){
	global $directoryName;
	global $appName;
	
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName);
	
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] == 'PRI' || $coloumnName['Key'] == 'PRIMARY'){
			$primaryKey = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']);
			$primaryKeyType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		}
	}
	
	$collectionClass="
<?php

namespace App\\{$directoryName}\\{$appName}\\{$studlyTableName};

use Fusio\\Adapter\\Sql\\Action\\SqlBuilderAbstract;
use Fusio\\Engine\\ContextInterface;
use Fusio\\Engine\\ParametersInterface;
use Fusio\\Engine\\RequestInterface;
use PSX\\Sql\\Builder;
use PSX\\Sql\\Condition;

/**
 * Action which returns a collection response of all {$camelTableName}. It shows how to
 * build complex nested JSON structures based on SQL queries
 */
class Collection extends SqlBuilderAbstract
{
    public function handle(RequestInterface \$request, ParametersInterface \$configuration, ContextInterface \$context)
    {
        /** @var \\Doctrine\\DBAL\\Connection \$connection */
        \$connection = \$this->connector->getConnection('System');  //** <<<< Please make sure to use the correct connection here <<< **/
        \$builder    = new Builder(\$connection);

        \$startIndex = (int) \$request->getParameter('startIndex');
        \$startIndex = \$startIndex <= 0 ? 0 : \$startIndex;
        \$condition  = \$this->getCondition(\$request);
		
		/** NEED to Customize the sql query here **/
        \$sql = 'SELECT \n";
		
	$fieldList="";
	foreach($coloumnNames as $coloumnName){
		$fieldList.= "						".$coloumnName['Field'].",\n";
	}
	$fieldList = substr($fieldList,0,strlen($fieldList)-2);  //remove the lastes comma
	
	$collectionClass.= $fieldList;
	
	$collectionClass.="
                  FROM {$database}.{$tableName}
                 WHERE 1=1
                   AND ' . \$condition->getExpression(\$connection->getDatabasePlatform()) . '
              ORDER BY {$tableName}.{$primaryKey} DESC';

        \$parameters = array_merge(\$condition->getValues(), ['startIndex' => \$startIndex]);
        \$definition = [
            'totalResults' => \$builder->doValue('SELECT COUNT(*) AS cnt FROM {$database}.{$tableName} WHERE 1 = 1', [], \$builder->fieldInteger('cnt')),
            'startIndex' => \$startIndex,
            'entries' => \$builder->doCollection(\$sql, \$parameters, [";
	
	
	$fieldListParams="";
	foreach($coloumnNames as $coloumnName){
		//$fieldList.= $coloumnName['Field'].",";
		$altDataType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		if($altDataType=='int'){
			$fieldListParams.="
				'{$coloumnName['Field']}' => \$builder->fieldInteger('{$coloumnName['Field']}'),";
		}
		if($altDataType=='string'){
			$fieldListParams.="
				'{$coloumnName['Field']}' => {$coloumnName['Field']},";
		}
		if($altDataType=='\Datetime'){
			$fieldListParams.="
				'{$coloumnName['Field']}' => \$builder->fieldDateTime('{$coloumnName['Field']}'),";
		}
	}
		
	$collectionClass.="
				{$fieldListParams}
                'links' => [
                    'self' => \$builder->fieldReplace('/{$camelTableName}/{{$primaryKey}}'),
                ]
            ])
        ];

        return \$this->response->build(200, [], \$builder->build(\$definition));
    }

    private function getCondition(RequestInterface \$request)
    {
        \$parameters = \$request->getParameters();
        \$condition  = new Condition();
		
		/** currently parameter is auto-generated for int & string field type only, others need to defined manually **/
        foreach (\$parameters as \$name => \$value) {
            switch (\$name) {";
	$cases="";
	foreach($coloumnNames as $coloumnName){
		$camelColoumnName = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']);
		$altDataType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		if(($altDataType=='int') && ($coloumnName['Key'] != 'PRI' || $coloumnName['Key'] != 'PRIMARY') ){
			$cases.="
                case '{$camelColoumnName}':
                    \$condition->equals('{$camelTableName}.{$camelColoumnName}', (int) \$value);
                    break;
				";
		}
		if($altDataType=='string'){
			$cases.="
                case '{$camelColoumnName}':
                    \$condition->like('{$camelTableName}.{$camelColoumnName}', '%' . \$value . '%');
                    break;
				";
		}
		
	}
	$collectionClass .=$cases;
	$collectionClass .="
            }
        }

        return \$condition;
    }
}
";
	writeFile($database,'Collection.php', $collectionClass, $studlyTableName);	
}


function generateEntityClass($tableName, $coloumnNames, $database){
	global $directoryName;
	global $appName;
	
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName);
	
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] == 'PRI' || $coloumnName['Key'] == 'PRIMARY'){
			$primaryKey= EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']);
			$primaryKeyType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		}
	}
	$entityClass="
<?php

namespace App\\\{$directoryName}\\{$appName}\\{$studlyTableName};

use Fusio\\Adapter\\Sql\\Action\\SqlBuilderAbstract;
use Fusio\\Engine\\ContextInterface;
use Fusio\\Engine\\ParametersInterface;
use Fusio\\Engine\\RequestInterface;
use PSX\\Sql\\Builder;

/**
 * Action which returns all details for a single {$camelTableName}
 */
class Entity extends SqlBuilderAbstract
{
    public function handle(RequestInterface \$request, ParametersInterface \$configuration, ContextInterface \$context)
    {
        /** @var \Doctrine\DBAL\Connection \$connection */
        \$connection = \$this->connector->getConnection('System'); //** <<<< Please make sure to use the correct connection here <<< **/
        \$builder    = new Builder(\$connection);

        \$sql = 'SELECT \n";
		
	$fieldList="";
	foreach($coloumnNames as $coloumnName){
		$fieldList.= "						".$coloumnName['Field'].",\n";
	}
	$fieldList = substr($fieldList,0,strlen($fieldList)-2);  //remove the lastes comma
	
	$entityClass.= $fieldList;	
	
	$entityClass.="
                  FROM {$tableName}
                 WHERE {$tableName}.{$primaryKey} = :{$primaryKey}';

        \$parameters = ['{$primaryKey}' => ({$primaryKeyType}) \$request->getUriFragment('{$camelTableName}_{$primaryKey}')];
        \$definition = \$builder->doEntity(\$sql, \$parameters, [";
	
	$fieldListParams="";
	foreach($coloumnNames as $coloumnName){
		//$fieldList.= $coloumnName['Field'].",";
		$altDataType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		if($altDataType=='int'){
			$fieldListParams.="
				'{$coloumnName['Field']}' => \$builder->fieldInteger('{$coloumnName['Field']}'),";
		}
		if($altDataType=='string'){
			$fieldListParams.="
				'{$coloumnName['Field']}' => {$coloumnName['Field']},";
		}
		if($altDataType=='\Datetime'){
			$fieldListParams.="
				'{$coloumnName['Field']}' => \$builder->fieldDateTime('{$coloumnName['Field']}'),";
		}
	}

	$entityClass .= $fieldListParams;
			
	$entityClass .="
			//--- if it has a children, should modify below, otherwise, delete it!!!
			'children' => \$builder->doCollection('SELECT id, ... FROM ... WHERE parent_{$primaryKey} = :parent', ['parent' =>  new Reference('{$primaryKey}')], [
                'id' => \$builder->fieldInteger('id'),
				// others need to defined here...
                'links' => [
                    'self' => \$builder->fieldReplace('/{$camelTableName}/{{$primaryKey}}'),
                    'parent' => \$builder->fieldReplace('/{$camelTableName}/{parent_{$primaryKey}}'),
                ]
            ]),
            'links' => [
                'self' => \$builder->fieldReplace('/{$camelTableName}/{{$primaryKey}}'),
            ]
        ]);

        return \$this->response->build(200, [], \$builder->build(\$definition));
    }
}	
";
	
	writeFile($database,'Entity.php', $entityClass, $studlyTableName);	
}

function getGetter($coloumnName,$coloumnType){
	$studlyColoumnName = EntityGenerator\Helper\HelperFunctions::studlyCaps($coloumnName);
	$camelColoumnName = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName);
	$altDataType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnType);
	$getter = 
"
	/**
	* @return {$altDataType}
	*/
    public function get{$studlyColoumnName}(): ?{$altDataType}{
        return \$this->{$camelColoumnName};
    }
\n";
	return $getter;
}

function getSetter($coloumnName,$coloumnType){
	$studlyColoumnName = EntityGenerator\Helper\HelperFunctions::studlyCaps($coloumnName);
	$camelColoumnName = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName);
	$altDataType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnType);
	$setter = 
"
	/**
	* @param {$altDataType} \${$camelColoumnName}
	*/
    public function set{$studlyColoumnName}(?{$altDataType} \${$camelColoumnName}): void{
        \$this->{$camelColoumnName} = \${$camelColoumnName};
        return \$this;
    }
\n";
	return $setter;
}

function genAttrComments($coloumnName,$coloumnType){
	$studlyColoumnName = EntityGenerator\Helper\HelperFunctions::studlyCaps($coloumnName);
	$camelColoumnName = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName);
	$altDataType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnType);
	$altDataType2 = $altDataType;
	
	
	$attrComments = 
"
	/**
	* @Key(\"{$studlyColoumnName}\")
	* @Type(\"{$altDataType}\")
	* @var {$altDataType2}
	*/\n";

	if($altDataType=='string'){
		$maxLength= EntityGenerator\Helper\HelperFunctions::getMaxStringLength($coloumnType);
		if($maxLength!=''){
				$attrComments = 
"
	/**
	* @Key(\"{$studlyColoumnName}\")
	* @Type(\"{$altDataType}\")
	* @MaxLength(\"{$maxLength}\")
	* @var {$altDataType2}
	*/\n";
		}
	}
	
	
	if($altDataType=='datetime'){
		$altDataType=='string'; //changes to string type
		$attrComments = 
"
	/**
	* @Key(\"{$studlyColoumnName}\")
	* @Type(\"{$altDataType}\")
	* @Format(\"date-time\")
	* @var {$altDataType2}
	*/\n";

	}

	return $attrComments;
}

// generate private vars & constructor function
function genConstructor($tableName){
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName); 
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	$constructor = 
"
	/**
     * @var {$studlyTableName}
     */
    private \${$camelTableName}Service;

    public function __construct({$studlyTableName} \${$camelTableName}Service)
    {
        \$this->{$camelTableName}Service = \${$camelTableName}Service;
    }
";
	return $constructor;
}

function genHandleFunc($tableName,$coloumnNames,$primaryKey,$primaryKeyType,$handleFor){
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName); 
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	
	$action="";
	if($handleFor=='create'){
		$action="\$this->{$camelTableName}Service->create(\$request->getBody()->getPayload(), \$context);";
	}
	if($handleFor=='update'){
		$action="\${$primaryKey} = ({$primaryKeyType}) \$request->getUriFragment('{$camelTableName}_{$primaryKey}');

            \$this->{$camelTableName}Service->update(\${$primaryKey}, \$request->getBody()->getPayload());";
	}
	if($handleFor=='delete'){
		$action="\${$primaryKey} = ({$primaryKeyType}) \$request->getUriFragment('{$camelTableName}_{$primaryKey}');

            \$this->{$camelTableName}Service->delete(\${$primaryKey});";
	}
	
	$handleFunc=
"
	public function handle(RequestInterface \$request, ParametersInterface \$configuration, ContextInterface \$context)
    {
		try {
			{$action}
		}catch (StatusCodeException \$e) {
            throw \$e;
        } catch (\\Throwable \$e) {
            throw new InternalServerErrorException(\$e->getMessage());
        }

        return \$this->response->build(201, [], \$body);
	}
        
";
	
	return $handleFunc;
}


function writeFile($database,$fileName, $content,$tableName){
	global $directoryName;
	$database = EntityGenerator\Helper\HelperFunctions::studlyCaps($database);
	if (!file_exists(__DIR__."/{$directoryName}/{$database}/{$tableName}/")) {
	    mkdir(__DIR__."/{$directoryName}/{$database}/{$tableName}/", 0777, true);
	}
	/*
	if(!defined('FILE_WRITE_PATH')){
		define('FILE_WRITE_PATH', __DIR__."/{$directoryName}/{$database}/{$tableName}/");
	}
	*/
	$file_write_path = __DIR__."/{$directoryName}/{$database}/{$tableName}/";
	
	//if($fh = fopen(FILE_WRITE_PATH.$fileName,'w+')){
	//	if(is_writable(FILE_WRITE_PATH.$fileName)){
	if($fh = fopen($file_write_path.$fileName,'w+')){
		if(is_writable($file_write_path.$fileName)){
			fwrite($fh, $content);
		}else{
			exit('Please provide Read and Write permissions for directory');	
		}
	}else{
		exit('Please provide Read and Write permissions for directory');
	}
}