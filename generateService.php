<?php
require_once 'init.php';
$directoryName = 'Service';
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
		generateServiceClass($table, $coloumnNames, $database);
	}
	header("Location:index.php?success=Successfully Generated Entities");
}else{
	header("Location:index.php?error=Oops! Error in generating entities.");
}

function generateServiceClass($tableName, $coloumnNames, $database){
	global $directoryName;
	global $appName;
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	
$serviceClass = 
"
<?php 
namespace App\\{$directoryName}\\{$appName};\n
use App\\{$directoryName}\\{$appName}\\{$studlyTableName} as Schema{$studlyTableName};
use Doctrine\DBAL\Connection;
use Fusio\Engine\ContextInterface;
use Fusio\Engine\DispatcherInterface;
use PSX\CloudEvents\Builder;
use PSX\Framework\Util\Uuid;
use PSX\Http\Exception as StatusCode;

class {$studlyTableName}{
	
";

	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] == 'PRI' || $coloumnName['Key'] == 'PRIMARY'){
			$primaryKey= EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']);
			$primaryKeyType = EntityGenerator\Helper\HelperFunctions::altDataType($coloumnName['Type']);
		}
	}

	//**** TO DO
	// generate vars
	// generate constructor
	$serviceClass .= genConstructor();
	// generate create function
	$serviceClass .= genCreateFunc($tableName,$coloumnNames,$primaryKey,$primaryKeyType);
	// generate update function 
	$serviceClass .= genUpdateFunc($tableName,$coloumnNames,$primaryKey,$primaryKeyType);
	// generate delete function 
	$serviceClass .= genDeleteFunc($tableName,$primaryKey,$primaryKeyType);
	// generate dispatch event function 
	$serviceClass .= genDispatchEventFunc($tableName,$primaryKey,$primaryKeyType);
	// generate assert function
	$serviceClass .= genAssertFunc($tableName,$coloumnNames);
	
	
$serviceClass .= "}";
	writeFile($database,$studlyTableName.'.php', $serviceClass);
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
function genConstructor(){
	$constructor = 
"
	/**
     * @var Connection
     */
    private \$connection;

    /**
     * @var DispatcherInterface
     */
    private \$dispatcher;
	
	public function __construct(Connection \$connection, DispatcherInterface \$dispatcher)
    {
        \$this->connection = \$connection;
        \$this->dispatcher = \$dispatcher;
    }
";
	return $constructor;
}

function genCreateFunc($tableName,$coloumnNames,$primaryKey,$primaryKeyType){
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName); 
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	
	$createFunc=
"
	public function create(Schema{$studlyTableName} \${$camelTableName}, ContextInterface \$context): {$primaryKeyType}
    {
        \$this->assert{$studlyTableName}(\${$camelTableName});

        \$this->connection->beginTransaction();

        try {
            \$data = [
";

	$dataFields="";
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] != 'PRI' && $coloumnName['Key'] != 'PRIMARY'){
			$fieldName = $coloumnName['Field']; 
			$camelFieldName = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']); 
			$studlyFieldName = EntityGenerator\Helper\HelperFunctions::studlyCaps($coloumnName['Field']); 
			$dataFields.=
"                '{$fieldName}' => \${$camelTableName}->get{$studlyFieldName}(),\n";
		}
	}
	
	$createFunc.= $dataFields .
"            ];
";
	$createFunc .=
"            \$this->connection->insert('{$tableName}', \$data);
            \${$primaryKey} = ({$primaryKeyType}) \$this->connection->lastInsertId();

            \$this->connection->commit();
        } catch (\\Throwable \$e) {
            \$this->connection->rollBack();

            throw new StatusCode\\InternalServerErrorException('Could not create a {$camelTableName}', \$e);
        }

        \$this->dispatchEvent('{$camelTableName}_created', \$data);

        return \${$primaryKey};
    }
";
	return $createFunc;
}

function genUpdateFunc($tableName,$coloumnNames,$primaryKey,$primaryKeyType){
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName); 
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName); 
	
	$updateFunc=
"
	public function update({$primaryKeyType} \${$primaryKey}, Schema{$studlyTableName} \${$camelTableName}): {$primaryKeyType}
    {
        \$row = \$this->connection->fetchAssoc('SELECT {$primaryKey} FROM {$tableName} WHERE {$primaryKey} = :{$primaryKey}', [
            '{$primaryKey}' => \${$primaryKey},
        ]);

        if (empty(\$row)) {
            throw new StatusCode\\NotFoundException('Provided {$camelTableName} does not exist');
        }

        \$this->assert{$studlyTableName}(\${$camelTableName});

        \$this->connection->beginTransaction();

        try {
            \$data = [
";
	$dataFields="";
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] != 'PRI' && $coloumnName['Key'] != 'PRIMARY'){
			$fieldName = $coloumnName['Field']; 
			$camelFieldName = EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']); 
			$studlyFieldName = EntityGenerator\Helper\HelperFunctions::studlyCaps($coloumnName['Field']); 
			$dataFields.=
"                '{$fieldName}' => \${$camelTableName}->get{$studlyFieldName}(),\n";
		} 
	}
	$updateFunc.= $dataFields .
"
            ];
";

	$updateFunc.=
"
            \$this->connection->update('{$tableName}', \$data, ['{$primaryKey}' => \${$primaryKey}]);

            \$this->connection->commit();
        } catch (\\Throwable \$e) {
            \$this->connection->rollBack();

            throw new StatusCode\\InternalServerErrorException('Could not update a {$camelTableName}', \$e);
        }

        \$this->dispatchEvent('{$camelTableName}_updated', \$data, \${$primaryKey});

        return \${$primaryKey};
    }
";
	return $updateFunc;
}

function genDeleteFunc($tableName,$primaryKey,$primaryKeyType){
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName); 
	$deleteFunc=
"
	public function delete({$primaryKeyType} \${$primaryKey}): {$primaryKeyType}
    {
        \$row = \$this->connection->fetchAssoc('SELECT {$primaryKey} FROM {$tableName} WHERE {$primaryKey} = :{$primaryKey}', [
            '{$primaryKey}' => \${$primaryKey},
        ]);

        if (empty(\$row)) {
            throw new StatusCode\\NotFoundException('Provided {$camelTableName} does not exist');
        }

        try {
            \$this->connection->delete('{$tableName}', ['{$primaryKey}' => \${$primaryKey}]);
        } catch (\\Throwable \$e) {
            \$this->connection->rollBack();

            throw new StatusCode\\InternalServerErrorException('Could not delete a {$camelTableName}', \$e);
        }

        \$this->dispatchEvent('{$camelTableName}_deleted', \$row, \${$primaryKey});

        return \${$primaryKey};
    }
";
	return $deleteFunc;
}

function genDispatchEventFunc($tableName, $primaryKey, $primaryKeyType){
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName);
	
	$dispatchEventFunc=
"
	private function dispatchEvent(string \$type, array \$data, ?{$primaryKeyType} \${$primaryKey} = null){
		\$event = (new Builder())
            ->withId(Uuid::pseudoRandom())
            ->withSource(\${$primaryKey} !== null ? '/{$camelTableName}/' . \${$primaryKey} : '/{$camelTableName}')
            ->withType(\$type)
            ->withDataContentType('application/json')
            ->withData(\$data)
            ->build();

        \$this->dispatcher->dispatch(\$type, \$event);
	}
";
	return $dispatchEventFunc;
}

function genAssertFunc($tableName,$coloumnNames){
	global $directoryName;
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	$camelTableName = EntityGenerator\Helper\HelperFunctions::camelCase($tableName);
	$assertFunc=
"
	private function assert{$studlyTableName}(Schema{$studlyTableName} \${$camelTableName})
    {
";
	$assertFields="";
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Null']=='NO'){
			$fieldName = $coloumnName['Field'];
			$camelFieldName=EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']);
			$studlyFieldName=EntityGenerator\Helper\HelperFunctions::studlyCaps($coloumnName['Field']);
			$assertFields.=
"
        \${$camelFieldName} = \${$camelTableName}->get{$studlyFieldName}();
        if (empty(\${$camelFieldName})) {
            throw new StatusCode\BadRequestException('No {$fieldName} provided');
        }
";
		}
	}
	
	$assertFunc .= $assertFields . "\n" .
"
    }
";
	
	return $assertFunc;
}


function writeFile($database,$fileName, $content){
	global $directoryName;
	$database = EntityGenerator\Helper\HelperFunctions::studlyCaps($database);
	if (!file_exists(__DIR__."/{$directoryName}/{$database}/")) {
	    mkdir(__DIR__."/{$directoryName}/{$database}/", 0777, true);
	}
	if(!defined('FILE_WRITE_PATH')){
		define('FILE_WRITE_PATH', __DIR__."/{$directoryName}/{$database}/");
	}
	if($fh = fopen(FILE_WRITE_PATH.$fileName,'w+')){
		if(is_writable(FILE_WRITE_PATH.$fileName)){
			fwrite($fh, $content);
		}else{
			exit('Please provide Read and Write permissions for directory');	
		}
	}else{
		exit('Please provide Read and Write permissions for directory');
	}
}