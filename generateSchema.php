<?php
require_once 'init.php';
$directoryName = 'Schema';
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
		generateSchemaClass($table, $coloumnNames, $database);
	}
	header("Location:index.php?success=Successfully Generated Entities");
}else{
	header("Location:index.php?error=Oops! Error in generating entities.");
}

function generateSchemaClass($tableName, $coloumnNames, $database){
	global $directoryName;
	$studlyTableName = EntityGenerator\Helper\HelperFunctions::studlyCaps($tableName);
	
$entityClass = 
"
<?php 
namespace {$directoryName};\n
class {$studlyTableName}{
	
";
	
	foreach($coloumnNames as $coloumnName){
		$entityClass .= "	". genAttrComments(EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']),$coloumnName['Type'])."";
		//$entityClass .= "    private \$".EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']).";\n";
		$entityClass .= "    protected \$".EntityGenerator\Helper\HelperFunctions::camelCase($coloumnName['Field']).";\n";
	}
	
	foreach($coloumnNames as $coloumnName){
		if($coloumnName['Key'] == 'PRI' || $coloumnName['Key'] == 'PRIMARY'){
			$entityClass .= getGetter($coloumnName['Field'],$coloumnName['Type']);
			continue;
		}else{
			$entityClass .= getGetter($coloumnName['Field'],$coloumnName['Type']);
			$entityClass .= getSetter($coloumnName['Field'],$coloumnName['Type']);
		}
	}
	
$entityClass .= "}";
	writeFile($database,$studlyTableName.'.php', $entityClass);
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