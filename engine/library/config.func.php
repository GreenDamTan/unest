<?php

/* 获得用户设置,见 readme.config.txt
 *
 */

class CfgParser{

	private static $_user_config = false;
	private static $_user_strength = false;
    private static $_preprocess_sec_name;
	private static $_user_params = false; //可 命令行输入 部分

	private static $_preprocess_config = false;   // 预处理设置
	private static $_user_cnf          = false;   // 用户配置(除预处理部分)	

	private static $_rdy               = false;   // rdy文件内容

    //////////////////////////////////////////////
	public static function unset_rdy(){
	    self::$_rdy = false;
	}

    //通用获取参数
	private static function get($array,$key){
		if (false === $key){
		    return $array;
		}elseif (!isset($array[$key])){
		    return false;
		}else{
		    return $array[$key];
		}	
	}
	//////////////////////////////////////////////
	public static function get_rdy($key = false){
	    return self::get(self::$_rdy,$key);
	}
	//////////////////////////////////////////////
	public static function get_user_cnf($key = false){
	    return self::get(self::$_user_cnf,$key);
	}
    //////////////////////////////////////////////
	public static function get_preprocess_config($key = false){
	    return self::get(self::$_preprocess_config,$key);
	}

    //////////////////////////////////////////////
	//
	//支持命令行/Get/Post 提交 的参数
	//返回    $ret['key'] = value
	//注：argv 优先于 REQUEST ,argv 参数序用双引号 generat.php "a=b&c=d..."
	//
	//type: (0) ready  (1) generate 与 self::init_params 有关
	public static function get_params($argv,$type=0){
		global $language;

		$ret = false;
	 
		if (is_array($_REQUEST)){
			$ret = $_REQUEST;
		}	
		if (count($argv) > 1){
			parse_str($argv[1],$ret);
		}		

		if (!isset($ret['cnf'])){
			GeneralFunc::LogInsert('need param cnf');
		}else{
			if (1 == $type){
			    $cf = @file_get_contents($ret['base'].'/'.$ret['rdy']);

	            if ($cf == false){
					GeneralFunc::LogInsert('fail to open ready file: '.$ret['base'].'/'.$ret['rdy']);
				}else{
				    self::$_rdy = unserialize($cf);//反序列化，并赋值  
				}
			}
            
			if (0 == $type){
				if (false === self::get_usr_config(false,$ret['base'].'/'.$ret['cnf'],true)){ //读取配置文件失败，不做任何处理？放弃
					GeneralFunc::LogInsert($language['without_cfg_file']);        
				}
			}elseif (1 == $type){
			    if (false === self::get_usr_config(self::$_rdy['sec_name'],$ret['base'].'/'.$ret['cnf'],false)){ //读取配置文件失败，不做任何处理？放弃
					GeneralFunc::LogInsert($language['without_cfg_file']);        
				}
			}

			self::$_user_params = $ret; 
			self::init_params($type);            //check && init params	

			if (1 == $type){  //setvalue_dynamic 动态调整 原设置值
	            self::affect_setvalue_dynamic(self::$_rdy['sec_name']);	
			}
            
			//var_dump (self::$_user_params);
			//var_dump (self::$_user_cnf);
		    //var_dump (self::$_preprocess_config);
			//exit;
		}
	}

	private static function init_params($type){	    
		if (!isset(self::$_user_params['timelimit'])){			
			GeneralFunc::LogInsert('need param timelimit');
		}
		if (!isset(self::$_user_params['base'])){
			GeneralFunc::LogInsert('need param base');
		}
		if (!isset(self::$_user_params['log'])){
		    GeneralFunc::LogInsert('need param log');
		}else{
		    self::$_user_params['log'] = self::$_user_params['base'].'/'.self::$_user_params['log'];
		}
		if (!isset(self::$_user_params['cnf'])){
			GeneralFunc::LogInsert('need param cnf');
		}else{
			self::$_user_params['cnf'] = self::$_user_params['base'].'/'.self::$_user_params['cnf']; //用户设置
		}
		if (0 == $type){ // ready 阶段
			if (!isset(self::$_user_params['path'])){
				GeneralFunc::LogInsert('need param path');
			}
			if ('bin' === self::$_user_params['type']){
				 self::$_user_params['type'] = 'BIN';
			}elseif ('coff' ===  self::$_user_params['type']){	
				 self::$_user_params['type'] = 'COFF';
			}else{
				GeneralFunc::LogInsert('need param type');
			}
			if (!isset(self::$_user_params['output'])){
				GeneralFunc::LogInsert('need param output');
			}else{
				self::$_user_params['_file'] = self::$_user_params['base'].'/'.self::$_user_params['output'].'/'.self::$_user_params['filename'];			
			}
			if (!isset(self::$_user_params['filename'])){
				GeneralFunc::LogInsert('need param filename');
			}else{
				self::$_user_params['filename'] = self::$_user_params['base'].'/'.self::$_user_params['path'].'/'.self::$_user_params['filename'];			
			}
		}elseif (1 == $type){ //gen 阶段					
			if (!isset(self::$_user_params['rdy'])){
				GeneralFunc::LogInsert('need param rdy');
			}else{
				self::$_user_params['rdy'] = self::$_user_params['base'].'/'.self::$_user_params['rdy']; 
			}			
			
			if (!isset(self::$_user_params['filename'])){
				GeneralFunc::LogInsert('need param filename');
			}else{
				self::$_user_params['obj_filename'] = self::$_user_params['base']."/".self::$_user_params['filename'];
				if (!is_file(self::$_user_params['obj_filename'])){
					GeneralFunc::LogInsert('file is not exist: '.self::$_user_params['obj_filename']);
				}
			}	

			if (!isset(self::$_user_params['outputfile'])){
				GeneralFunc::LogInsert('need param outputfile');
			}else{
				self::$_user_params['_file'] = self::$_user_params['base'].'/'.self::$_user_params['outputfile'];	
			}
		}	
	}

	public static function params($key = false){
		return self::get(self::$_user_params,$key);
	}

    ////////////////////////////////////////////////////////////////////////////////
	//
	public static function GetPreprocess_sec(){
	    return self::$_preprocess_sec_name;
	}
    ////////////////////////////////////////////////////////////////////////////////
	//比较 相对 ready 阶段，$_preprocess_sec_name 是否有新增
    public static function CmpPreprocess_sec($c_array){
		global $language;

	    foreach ($c_array as $a => $b){
		    if (!isset(self::$_preprocess_sec_name[$a])){
			    GeneralFunc::LogInsert($language['new_sec_increase_rdy'].$a);
			}
		}
	}

	////////////////////////////////////////////////////////////////////////////////
	//获取强度设置 (by section)
	public static function GetStrength($sec){
	    if (isset(self::$_user_strength[$sec])){
		    return self::$_user_strength[$sec];
		}
		return false;
	}

	////////////////////////////////////////////////////////////////////////////////
	//
	//根据用户configure文件生成 数组(按节表)
	//
	private static function usr_config_parser($buffer,$preprocess,&$all_configure_array,&$section_array,&$global_array,&$i){

		global $language;


		$in_section = false;  //正在处理一个设置段(true),无设置段在处理(false)
		$c_name     = false;  //当前段名
		$c_ret      = array();//保存当前设置，段定义结束时 赋予 变量

		foreach ($buffer as $line => $a){  
			$line ++ ;// 显示的行数从 1 开始
			$a = trim($a);
			if ((0 == strlen($a)) or ('//' === substr($a,0,2))){ //空行 或 注释行
				continue;
			}
			if (false === $in_section){
				if ('==SectionConfig==' === $a){
					$c_ret  = array();
					$c_name = false;
					$in_section = 1;    
					continue;
				}elseif ('==GlobalConfig==' === $a){
					$in_section = 2;    
					continue;
				}elseif ('==PreprocessConfig==' === $a){
					if (false !== self::$_preprocess_config){  //预处理 设置 不止 一个段
						GeneralFunc::LogInsert($language['dup_preprocessconfig']);
						return false;
					}
					$in_section = 3; 
					continue;
				}				    
			}else{
				if (('==/SectionConfig==' === $a)&&(1 === $in_section)){
					if (false !== $c_name){
						$all_configure_array[$i] = $c_ret;
						if (isset($section_array[$c_name])){
							GeneralFunc::LogInsert($language['dup_section'].$c_name);
							return false;
						}
						$section_array[$c_name] = $i;
						$i ++;
					}
					$c_name = false;
					$in_section = false;
					$c_ret  = array();
					continue;
				}elseif (('==/GlobalConfig==' === $a)&&(2 === $in_section)){
					$all_configure_array[$i]           = $c_ret;
					$global_array[$i] = $i;
					$i ++;
					$in_section = false;
					$c_ret  = array();
					continue;
				}elseif (('==/PreprocessConfig==' === $a)&&(3 === $in_section)){
					$in_section = false;
					continue; 
				}else{
					$tmp = preg_split('/\s+/',$a); 
					if (count($tmp) > 1){
						$name  = trim ($tmp[0]);							
						$value = trim ($tmp[1]);
			
						if (3 === $in_section) { //预处理 部分
							if ('@protect_section' === $name){
								$size = intval($tmp[2]); // 长度
								$value = intval($value);
								if (!isset(self::$_preprocess_config[$value]) or ($size > self::$_preprocess_config[$value])){
									self::$_preprocess_config['protect_section'][$value] = $size;
								}									
							}elseif ('@dynamic_insert' === $name){
								$size = intval($tmp[2]); // 长度
								$value = intval($value);
								if (!isset(self::$_preprocess_config[$value]) or ($size > self::$_preprocess_config[$value])){
									self::$_preprocess_config['dynamic_insert'][$value] = $size;
								}
							}	
							continue;
						}else{
							//if (true === $preprocess){ //仅处理preprocess
							//	continue;              //预处理部分不处理非preprocess的设置项(因为非preprocess设置可在ready完成后,gen之前随时修改)
							//}
							if ('@name' === $name){
								if (false === $c_name){
									$c_name = $value;								
								}else{ //多个 sec name 设置
									GeneralFunc::LogInsert($language['double_usr_cfg_secname'].' '.$line.' : '.$a,2);
								}
								continue;
							}else{
								$tmp = self::check_cnf_value ($name,$value,$c_ret);
								if (1 === $tmp){
									GeneralFunc::LogInsert($language['unknown_usr_cfg_name'].' '.$line.' : '.$a,2);
								}elseif (2 === $tmp){
									// 值 类型非法
									GeneralFunc::LogInsert($language['dismatch_usr_cfg_value'].' '.$line.' : '.$a,2);
								}
								continue;				
							}
						}
					}
				}
			}
			GeneralFunc::LogInsert($language['unkown_usr_cfg_line'].' '.$line.' : '.$a,3);
		}

	}
	////////////////////////////////////////////////////////////////////////////////
	//
	//读取用户设置文件内容
	//
	//$preprocess = true 仅处理preprocess 部分
	//

	public static function get_usr_config($sec_name,$filename,$preprocess = false){

		global $language;


		$buffer = false;
		$handle = @fopen("$filename", "r");
		if ($handle) {
			while (!feof($handle)) {
				$buffer[] = fgets($handle, 4096);			
			}
			fclose($handle);

			if (is_array($buffer)){
				$all_configure_array = array(); //$key => 'global' = true, //全局
												//        '...'    = xxxx,
												//
				$section_array = array();       //$sec_name => $all_configure_array 编号
												//
				$global_array = array();        //$all_configure_array 编号 => $all_configure_array 编号
												//
				//$preprocess_config = false;     //预处理 => 数组
												//
				$index = 1;                     //$all_configure_array 编号
												//
				self::usr_config_parser($buffer,$preprocess,$all_configure_array,$section_array,$global_array,$index);  

				//if (true === $preprocess){ //预处理,搜集所有目标 段名
				foreach ($section_array as $a => $b){
					self::$_preprocess_sec_name[$a] = true;
				}
				//}
				
				if (false === $preprocess){
					//有configure 未定义 section,放到$all_configure_array数组最后，等待global扩展
					if (is_array($sec_name)){
						foreach ($sec_name as $c_name => $v){
							if (!isset($section_array[$c_name])){
								$all_configure_array[$index] = array();
								$section_array[$c_name] = $index;
								$index ++;
							}
						}
					}
					//configure中存在，单目标文件中不存在的section name
					foreach ($section_array as $c_name => $v){
						if (!isset($sec_name[$c_name])){
							unset ($all_configure_array[$v]);
							GeneralFunc::LogInsert($language['unknown_cfg_sec_name'].$c_name,2);
						}
					}

					//global 设置 向下扩展(按次序)
					self::global_configure_extended ($all_configure_array,$global_array);

					//分配configure 内容，返回
					if (is_array($sec_name)){
						foreach ($sec_name as $c_name => $v){
							$i = $section_array[$c_name];
							self::$_user_config[$c_name]            = $all_configure_array[$i]['unprotect'];
							self::$_user_config[$c_name]['protect'] = $all_configure_array[$i]['protect'];
							if (true === $all_configure_array[$i]['stack_usable']){
								self::$_user_config[$c_name]['stack_usable'] = true;
							}
							foreach ($v as $sec_id){
								self::$_user_cnf[$sec_id]              = $all_configure_array[$i];
								self::$_user_strength[$sec_id]  = $all_configure_array[$i]['strength'];
							}
						}		
					}
				}
				return true;
			}
		}
		return false;
	}
	////////////////////////////////////////////////////////////////////////////////
	//global 设置 向下扩展(按次序)
	private static function global_configure_extended (&$all_configure_array,$global_array){
		$a = $all_configure_array;
		$c_global = array(); //当前全局设置
		foreach ($a as $key => $value){
			if (isset($global_array[$key])){ //全局设置 ?
				self::patch_array($value,$c_global);
				$c_global = $value;
				unset ($all_configure_array[$key]);
			}elseif (!empty($c_global)){        //继承全局设置
				self::patch_array($all_configure_array[$key],$c_global);
			}
		}
	}

	////////////////////////////////////////////////////////////////////////////////
	//
	// 多维数组 补充
	// 把第二参数 新增部分(第一参数未定义) 添加给 第一参数
	//
	private static function patch_array(&$a,$b){
		if (!is_array($a)){
			$a = $b;
		}elseif (is_array($b)){
			foreach ($b as $z => $y){
				if (!isset($a[$z])){
					$a[$z] = $b[$z];
				}elseif (is_array($y)){
					self::patch_array($a[$z],$b[$z]);
				}
			}
		}

		return;
	}


	////////////////////////////////////////////////////////////////////////////////
	//
	//判断value是否类型相符，符合则返回  true 并赋值 给$c_ret
	//                       不符合 返回 1: 未知的 key name
	//                                   2：value 类型不符
	//
	private static function check_cnf_value ($name,$value,&$c_ret){
		

		if ('@unprotect' === $name){
			$value = strtoupper($value);
			if ('STATUS_FLAGS' === $value){		
				if (false !== ($a = Instruction::getEflags(1))){
				    foreach ($a as $b){
				        $c_ret['unprotect'][FLAG][$b] = 1;
					}
				}
				return true;
			}elseif (Instruction::isEflag($value)){
				$c_ret['unprotect'][FLAG][$value] = 1;
				return true;
			}elseif (Instruction::getRegByIdxBits(32,$value)){
				$c_ret['unprotect'][NORMAL][$value]['32'] = 1;
				return true;
			}								 
		}elseif ('@protect' === $name){
			$value = strtolower($value);
			if ('thread_memory' === $value){
				$c_ret['protect']['thread_memory'] = true;  
				return true;
			}
		}elseif ('@strength_poly_max' === $name){
			if (is_numeric($value)){
				$c_ret['strength'][POLY]['max'] = intval($value);
				return true;
			}
		}elseif ('@strength_poly_min' === $name){
			if (is_numeric($value)){
				$c_ret['strength'][POLY]['min'] = intval($value);
				return true;
			}
		}elseif ('@strength_bone_max' === $name){
			if (is_numeric($value)){
				$c_ret['strength'][BONE]['max'] = intval($value);
				return true;
			}
		}elseif ('@strength_bone_min' === $name){
			if (is_numeric($value)){
				$c_ret['strength'][BONE]['min'] = intval($value);
				return true;
			}
		}elseif ('@strength_meat_max' === $name){
			if (is_numeric($value)){
				$c_ret['strength'][MEAT]['max'] = intval($value);
				return true;
			}
		}elseif ('@strength_meat_min' === $name){
			if (is_numeric($value)){
				$c_ret['strength'][MEAT]['min'] = intval($value);
				return true;
			}
		}elseif ('@strength_default' === $name){
			if (is_numeric($value)){
				$c_ret['strength']['default'] = intval($value);
				return true;
			}
		}elseif ('@meat_mutation' === $name){
			if (is_numeric($value)){
				$c_ret['meat_mutation'] = intval($value);
				return true;
			}
		}elseif ('@soul_focus' === $name){
			if (is_numeric($value)){
				$c_ret['soul_focus'] = intval($value);
				return true;
			}
		}elseif ('@output_opcode_max' === $name){
			if (is_numeric($value)){
				$c_ret['output_opcode_max'] = intval($value);
				return true;
			}
		}elseif ('@setvalue_dynamic' === $name){
			$value = strtolower($value);
			$c_ret['setvalue_dynamic'][$value] = true;
			return true;	
		}elseif ('@gen4debug01' === $name){
			if ('on' === strtolower($value)){
				$c_ret['gen4debug01'] = true;
				return true;
			}		
		}elseif ('@stack_usable' === $name){
			if ('on' === strtolower($value)){
				$c_ret['stack_usable'] = true;
				return true;
			}
		}elseif ('@stack_pointer_define' === $name){
			$value = strtoupper($value);
			if (Instruction::getRegByIdxBits(32,$value)){
				$c_ret['stack_pointer_define'][$value] = $value;
				return true;
			}		
		}else{  //未知 变量名
			return 1;
		}
		return 2;
	}

	////////////////////////////////////////////////////////////////////////////////
	//
	// revalue_dynamic 设置
	//                 generate.php参数 覆写 configure 内容
	//
	private static function affect_setvalue_dynamic($sec_name){

		global $language;

        if (isset(self::$_user_params['sd'])){
			$setvalue_dynamic = self::$_user_params['sd'];

			foreach ($setvalue_dynamic as $name => $a){
				if (isset($sec_name[$name])){
					$v = $sec_name[$name];
					foreach ($v as $a){
						foreach ($setvalue_dynamic[$name] as $key => $new_value){
							if (true === self::$_user_cnf[$a]['setvalue_dynamic'][$key]){
								$tmp = self::check_cnf_value('@'.$key,$new_value,self::$_user_cnf[$a]);
								if (1 === $tmp){ //key name unknown
									GeneralFunc::LogInsert($language['setvalue_unknown_key'].'['.$name.']['.$key.'] = '.$new_value,2);
								}elseif (2 === $tmp){ //value mismatch
									GeneralFunc::LogInsert($language['setvalue_mismatch_value'].'['.$name.']['.$key.'] = '.$new_value,2);
								}						
							}else{ //尝试向未开放动态revalue的段中 动态覆写					
								GeneralFunc::LogInsert($language['setvalue_with_off'].'['.$name.']['.$key.']',2);
								break;
							}
						}
					}
				}else{ //传送了一个未定义段名的revalue
					GeneralFunc::LogInsert($language['setvalue_illegal_sec'].$name,2);
				}
			}
		}

	}

	////////////////////////////////////////////////////////////////////////////////
	//
	//根据 用户 对 节表 定义，对 soul_usable 进行增加 (除soul_forbid 显式禁止的外)
	//
	public static function reconfigure_soul_usable ($sec_name,$soul_writein_Dlinked_List_Total,&$soul_usable,$soul_forbid){
		global $StandardAsmResultArray;
		global $all_valid_mem_opt_index;
		global $avmoi_ptr;

		foreach ($sec_name as $a => $b){	    
			if (empty(self::$_user_config[$a])){
				continue;
			}
		
			$c_define = self::$_user_config[$a]; 
			foreach ($b as $c => $d){
				$c_list = $soul_writein_Dlinked_List_Total[$d]['list'][0]; //未 多态/混淆 ，起始位默认是0
				while (true){				
					$f = $c_list[C]; 
					if (true === $c_define['protect']['thread_memory']){   //禁止所有内存地址 可写入 属性
						if (is_array($soul_usable[$d][$f][P][MEM_OPT_ABLE])){
							foreach ($soul_usable[$d][$f][P][MEM_OPT_ABLE] as $z => $y){
								if (1 < $all_valid_mem_opt_index[$y][OPT]){
									$all_valid_mem_opt_index[$avmoi_ptr] = $all_valid_mem_opt_index[$y];
									$all_valid_mem_opt_index[$avmoi_ptr][OPT] &= 1;
									$soul_usable[$d][$f][P][MEM_OPT_ABLE][$z] = $avmoi_ptr;
									$avmoi_ptr ++;
								}
							}
						}
						if (is_array($soul_usable[$d][$f][N][MEM_OPT_ABLE])){
							foreach ($soul_usable[$d][$f][N][MEM_OPT_ABLE] as $z => $y){
								if (1 < $all_valid_mem_opt_index[$y][OPT]){
									$all_valid_mem_opt_index[$avmoi_ptr] = $all_valid_mem_opt_index[$y];
									$all_valid_mem_opt_index[$avmoi_ptr][OPT] &= 1;
									$soul_usable[$d][$f][N][MEM_OPT_ABLE][$z] = $avmoi_ptr;
									$avmoi_ptr ++;
								}
							}
						}
					}
					//foreach ($StandardAsmResultArray[$d] as $f => $g){				
					if (isset($c_define[FLAG])){
						foreach ($c_define[FLAG] as $z => $y){
							if (!isset($soul_forbid[$d][$f][P][FLAG][$z])){
								$soul_usable[$d][$f][P][FLAG_WRITE_ABLE][$z] = $y;
							}
							if (!isset($soul_forbid[$d][$f][N][FLAG][$z])){
								$soul_usable[$d][$f][N][FLAG_WRITE_ABLE][$z] = $y;
							}
						}
					}
					//通用寄存器，方便起见，forbid显式禁止 以寄存器为单位，不细分到位
					if (isset($c_define[NORMAL])){
						foreach ($c_define[NORMAL] as $z => $y){
							if (!isset($soul_forbid[$d][$f][P][NORMAL][$z])){
								foreach ($c_define[NORMAL][$z] as $x => $w){
									$soul_usable[$d][$f][P][NORMAL_WRITE_ABLE][$z][$x] = $y;
								}
							}
							if (!isset($soul_forbid[$d][$f][N][NORMAL][$z])){
								foreach ($c_define[NORMAL][$z] as $x => $w){
									$soul_usable[$d][$f][N][NORMAL_WRITE_ABLE][$z][$x] = $y;
								}
							}
						}
					}

					if (true === $c_define['stack_usable']){   //设置所有栈有效 and 清除所有单位可用ESP,  见./readme/readme.config.txt
						unset ($soul_usable[$d][$f][P][NORMAL_WRITE_ABLE]['ESP']);
						unset ($soul_usable[$d][$f][N][NORMAL_WRITE_ABLE]['ESP']);
						$soul_usable[$d][$f][P][STACK] = true;
						$soul_usable[$d][$f][N][STACK] = true;
						$StandardAsmResultArray[$d][$f][STACK] = true;
					}
					///////////////////////////////////////////////////////
					//
					if (isset($c_list[N])){
						$c_list = $soul_writein_Dlinked_List_Total[$d]['list'][$c_list[N]];
					}else{
						break;
					}
				}			
			}
		}
	}

    //显示命令行设置
	public static function params_show(){
		echo "<br>============= 显示用户命令行输入 ============= <br>";
		echo '<table border = 1><tr>';
		foreach (self::$_user_params as $i => $v){
		    echo '<tr><td>';
			echo "$i";
			echo '</td><td>';
			var_dump ($v);
			echo '</td></tr>';
		}
		echo '</table>';
	}

	//显示用户设置
	public static function show($sec_name){
		echo "<br>============= 显示用户设置 ( ".self::$_user_params['base'].'/'.$_user_params['cnf']." ) ============= <br>";
		foreach ($sec_name as $sec_name_show => $a){
			$save = false;
			$total_sec_num = array();
			$table_name = array();
			$table_value = array();
			$table_name[] = "sec name";
			$table_value[] = $sec_name_show;
			$table_name[] = "sec number";		
			foreach ($a as $sec_num){	
				$total_sec_num[$sec_num] = $sec_num;    
			}
			$table_value[] = $total_sec_num;
			
			$a = $sec_num;
			$b = self::$_user_cnf[$a];
					
			if (is_array($b)){
				foreach ($b as $z => $y){
					$table_name[]  = $z;
					$table_value[] = $y;
				}
			}else{
				$table_name[]  = $a;
				$table_value[] = $b;
			}


			echo '<table border = 1><tr>';
			foreach ($table_name as $i => $a){			
				if ($i%2){
					echo "<td>";
				}else{
					echo "<td bgcolor='#c0c0c0'>";
				}
				echo "$a"."</td>";
			}
			echo '</tr><tr>';
			foreach ($table_value as $i=> $a){			
				if ($i%2){
					echo "<td>";
				}else{
					echo "<td bgcolor='#c0c0c0'>";
				}
				var_dump ($a);
				echo '</td>';
			}        		
			echo '</tr>';
			echo '</table><br>';	
		}	

	}

}

?>