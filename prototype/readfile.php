<?php
	/*Setup*/
	require_once "eos.class.php";

	/*Validate Paramiters*/
	if ($argc != 2)
		die("Error: Please enter a single filename to be read" . PHP_EOL);
	if (!file_exists($argv[1]))
		die("Error: No such file {$argv[1]}" . PHP_EOL);
	if (!is_readable($argv[1]))
		die("Error: File is not readable. Check permissions." . PHP_EOL);

	/*Read and Process File*/
	$file = fopen($argv[1], "r");
	while (!feof($file) && ($line = fgets($file))) {
		$line = trim(preg_replace('/\s+/', ' ', (preg_replace('/\t+/', ' ', $line))));
		if (strcspn($line, "#"))
			$line = trim(substr($line, 0, strcspn($line, "#")));
		else if (empty($fact) || $line[0] == "#" || $line == NULL)
			continue;
		if (strcspn($line, "=>") > 0 && $line[0] !== "?") {
			if (strpos($line, "<=>")) {
				$tmp = explode("<=>", $line);
				$rules[] = "{$tmp[0]} => {$tmp[1]}";
				$rules[] = "{$tmp[1]} => {$tmp[0]}";
			}
			else
				$rules[] = $line;
		}
		else if ($line[0] == "=" && $line[1] != ">")
			$facts[] = $line;
		else if ($line[0] == "?")
			$queries[] = $line;
		else
			print("Invalid Line: [$line]" . PHP_EOL);
	}
	if (!$rules[0] || ! $facts[0] || !$queries[0])
		die("Error: The file does not contain all three rules, facts, and queries." . PHP_EOL);
	fclose($file);

	/*Validate Rules*/
	foreach ($rules as $rule) {
		if (strpos($rule, "=>") != strpos($rule, "=>"))
			die ("Error: Multiple implications per rule." . PHP_EOL);
		if (strpos($rule, "<=>"))
			$tmp = explode("<=>", $rule);
		else if (strpos($rule, "==>"))
			$tmp = explode("==>", $rule);
		else
			$tmp = explode("=>", $rule);
		if (!trim($tmp[0]) || !trim($tmp[1]))
			die("Error: Invalid rule [$rule], missing expression" . PHP_EOL);
		if (!preg_match("/[A-Z]/i", $tmp[0]) || !preg_match("/[A-Z]/i", $tmp[1]))
			die("Error: Invalid rule [$rule], missing variables" . PHP_EOL);
		if (preg_replace("/([A-Z\ \+\!\-\|\^\(\)])/i", "", $tmp[0]) != NULL)
			die("Error: Invalid rule [$rule], invalid character(s)" . PHP_EOL);
		if (preg_replace("/([A-Z\ \+\!\-\|\^\(\)])/i", "", $tmp[1]) != NULL)
			die("Error: Invalid rule [$rule], invalid character(s)" . PHP_EOL);
		try {
			$eq = new eqEOS();
			$a = $eq->solveIF(preg_replace("/[\|\^]/", "-", preg_replace("/[A-Z]/", 1, $tmp[0])));
			$b = $eq->solveIF(preg_replace("/[\|\^]/", "-", preg_replace("/[A-Z]/", 1, $tmp[1])));
			if (is_numeric($a) === FALSE || is_numeric($b) === FALSE)
				throw new Exception ("");
		}
		catch (Exception $e) {
			die("Error: Invalid rule [$rule], invalid syntax" . PHP_EOL);
		}
	}

	/*Validate Facts*/
	foreach ($facts as $fact) {
		if (preg_replace("/([A-Z\ ])/i", "", $fact) !== "=")
			die("Error: Invalid initial fact [$fact]" . PHP_EOL);
	}

	/*Validate Queries*/
	foreach ($queries as $querie) {
		if (preg_replace("/([A-Z\ ])/i", "", $querie) !== "?")
			die("Error: Invalid querie [$querie]" . PHP_EOL);
	}

	/*Generate done function*/
	$fn_done[0] = "char\tdone(void)";
	$fn_done[1] = "{";
	foreach ($facts as $fact) {
		$fact = strtolower($fact);
		$k = 0;
		$l = strlen($fact);
		while (!empty($fact) && (++$k) < $l) {
			$fn_done[] = "\tif ({$fact[$k]} != 0 && {$fact[$k]} != 1)";
			$fn_done[] = "\t\treturn (1);";
		}
	}
	$fn_done[] = "\treturn (0);";
	$fn_done[] = "}";

	/*Generate trues and display function*/
	$fn_trues[0] = "void\ttrues(void)";
	$fn_trues[1] = '{';
	$fn_display[0] = "void\tdisplay(void)";
	$fn_display[1] = "{";
	foreach ($queries as $querie) {
		$querie = strtolower($querie);
		$k = 0;
		$l = strlen($fact);
		while (!empty($fact) && (++$k) < $l) {
			$fn_trues[] = "\t{$querie[$k]} = 2;";
			$fn_display[] = "\tif ({$querie[$k]})";
			$fn_display[] = '		write(1, "' . $querie[$k] . ' is true.\n", 11);';
			$fn_display[] = "\telse";
			$fn_display[] = '		write(1, "' . $querie[$k] . ' is false.\n", 12);';
		}
	}
	$fn_display[] = "}";
	$fn_trues[] = "}";

	/*Generate rules function*/
	$fn_rules[0] = "void\trules(void)";
	$fn_rules[1] = "{";
	foreach ($rules as $rule) {
		$rule = strtolower($rule);
		if (preg_match("/==>/", $rule)) {
			$r = explode("==>", $rule);
			$r[0] = trim($r[0]);
			$r[0] = str_replace("+", "&&", $r[0]);
			$r[0] = str_replace("|", "||", $r[0]);
			//$r[0] = str_replace("^", "^^", $r[0]);//need an eqivelent
			$q = preg_replace("/[a-z ]/", "", $r[1]);
			if ($q == NULL) {
				$r[1] = trim($r[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_var({$r[1]}, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_var({$r[1]}, 0);";
			}
			else if ($q == "!") {
				$r[1] = trim(str_replace("!", "", $r[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_var({$r[1]}, 0);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_var({$r[1]}, 1);";
			}
			else if ($q == "&") {
				$r[1] = trim($r[1]);
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
			}
			else if ($q == "|") {
				$r[1] = trim($r[1]);
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
			}
			else if ($q == "^") {
				$r[1] = trim($r[1]);
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_xor_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_xor_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
			}
			else if ($q == "!&") {
				$r[1] = trim($r[1]);
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
			}
			else if ($q == "&!") {
				$r[1] = trim($r[1]);
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
			}
			else if ($q == "!&!") {
				$r[1] = trim($r[1]);
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
			}
			else if ($q == "!|") {
				$r[1] = trim($r[1]);
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
			}
			else if ($q == "|!") {
				$r[1] = trim($r[1]);
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
			}
			else if ($q == "!|!") {
				$r[1] = trim($r[1]);
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
			}
			else if (preg_replace("/[\+\!]/", "", $q) == NULL) {
				$tmp = preg_replace("/[+ ]/", "", $r[1]);
				$neg = 1;
				$k = -1;
				$l = strlen($tmp);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t{";
				while (!empty($tmp) && (++$k) < $l) {
					if ($tmp[$k] == "!")
						$neg = 0;
					else {
						$fn_rules[] = "\t\tset_var(&{$tmp[$k]}, {$neg});";
						$neg = 1;
					}
				}
				$fn_rules[] = "\t}";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t{";
				$neg = 0;
				$k = -1;
				while (!empty($tmp) && (++$k) < $l) {
					if ($tmp[$k] == "!")
						$neg = 1;
					else {
						$fn_rules[] = "\t\tset_var(&{$tmp[$k]}, {$neg});";
						$neg = 0;
					}
				}
				$fn_rules[] = "\t}";
			}
			//
		}
		else {
			$r = explode("=>", $rule);
			$r[0] = trim($r[0]);
			$r[0] = str_replace("+", "&&", $r[0]);
			$r[0] = str_replace("|", "||", $r[0]);
			//$r[0] = str_replace("^", "^^", $r[0]);//need an eqivelent
			$q = preg_replace("/[a-z ]/", "", $r[1]);
			if ($q == NULL) {
				$r[1] = trim($r[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_var(&{$r[1]}, 1);";
			}
			else if ($q == "!") {
				$r[1] = trim(str_replace("!", "", $r[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_var(&{$r[1]}, 0);";
			}
			else if ($q == "+") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
			}
			else if ($q == "|") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
			}
			else if ($q == "^") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_xor_case(&{$tmp[0]}, &{$tmp[1]}, 1, 1);";
			}
			else if ($q == "!+") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
			}
			else if ($q == "+!") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
			}
			else if ($q == "!+!") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
			}
			else if ($q == "!|") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
			}
			else if ($q == "|!") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
			}
			else if ($q == "!|!") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
			}
			else if ($q == "!^") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_xor_case(&{$tmp[0]}, &{$tmp[1]}, 0, 1);";
			}
			else if ($q == "^!") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_xor_case(&{$tmp[0]}, &{$tmp[1]}, 1, 0);";
			}
			else if ($q == "!^!") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim(str_replace("!", "", $tmp[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_xor_case(&{$tmp[0]}, &{$tmp[1]}, 0, 0);";
			}
			else if (preg_replace("/[\+\!]/", "", $q) == NULL) {
				$tmp = preg_replace("/[+ ]/", "", $r[1]);
				$neg = 1;
				$k = -1;
				$l = strlen($tmp);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t{";
				while (!empty($tmp) && (++$k) < $l) {
					if ($tmp[$k] == "!")
						$neg = 0;
					else {
						$fn_rules[] = "\t\tset_var(&{$tmp[$k]}, {$neg});";
						$neg = 1;
					}
				}
				$fn_rules[] = "\t}";
			}
			//
		}
	}
	$fn_rules[] = "}";

	/*Write to file*/
	$command = system("cp ./C\ Program/share.cpp ./expert_system.cpp");
	/*writing the $fn_done, $fn_true, $fn_rules to same file*/
	$fd = fopen("./expert_system.cpp", "a+");
	foreach ($fn_done as $line) {
		fwrite($fd, $line . "\n");
	}
	fwrite($fd, "\n");
	foreach ($fn_trues as $line) {
		fwrite($fd, $line . "\n");
	}
	fwrite($fd, "\n");
	foreach ($fn_rules as $line) {
		fwrite($fd, $line . "\n");
	}
	fwrite($fd, "\n");
	foreach ($fn_display as $line) {
		fwrite($fd, $line . "\n");
	}
	fwrite($fd, "\n");

	/*compile expert_system.c*/
	$command = system("g++ expert_system.cpp -o expert_system", $retval);
	echo "retval = $retval \n";
	if ($retval === 1)
		die("Syntax Error in rules." . PHP_EOL);
	$command = system("./expert_system");
	/*Debug (uncomment to use)*/
	//var_dump($rules, $facts, $queries);
	//var_dump($fn_done, $fn_trues, $fn_display, $fn_rules);
?>
