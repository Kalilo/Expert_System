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
		if (strcspn($line, "=>") > 0 && $line[0] !== "?")
			$rules[] = $line;
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
		$tmp = explode((strpos($rule, "<=>") ? "<=>" : "=>"), $rule);
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
	$fn_done[0] = "int\tdone(void)";
	$fn_done[1] = "{";
	foreach ($facts as $fact) {
		$k = 0;
		$l = strlen($fact);
		while (!empty($fact) && (++$k) < $l) {
			$fn_done[] = "\tif ({$fact[$k]} != 0 && {$fact[$k]} != 1)";
			$fn_done[] = "\t\treturn (1);";
		}
	}
	$fn_done[] = "\treturn (0);";
	$fn_done[] = "}";

	/*Generate trues function*/
	$fn_trues[0] = "int\ttrues(void)";
	$fn_trues[1] = '{';
	foreach ($queries as $querie) {
		$k = 0;
		$l = strlen($fact);
		while (!empty($fact) && (++$k) < $l) {
			$fn_trues[] = "\t{$querie[$k]} = 2;";
		}
	}
	$fn_trues[] = "\treturn (0);";
	$fn_trues[] = "}";

	/*Generate rules function*/  
	$fn_rules[1] = "{";
	foreach ($rules as $rule) {
		if (preg_match("/<=>/", $rule)) {
			$r = explode("<=>", $rule);
			$r[0] = trim($r[0]);
			$r[0] = str_replace("+", "&&", $r[0]);
			$r[0] = str_replace("|", "||", $r[0]);
			//$r[0] = str_replace("^", "^^", $r[0]);//need an eqivelent
			$q = preg_replace("/[A-Z ]/", "", $r[1]);
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
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 0);";
			}
			else if ($q == "|") {
				$r[1] = trim($r[1]);
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 0);";
			}
			else if ($q == "^") {
				$r[1] = trim($r[1]);
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 1);";
				$fn_rules[] = "\telse";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 0);";
			}
			//
		}
		else {
			$r = explode("=>", $rule);
			$r[0] = trim($r[0]);
			$r[0] = str_replace("+", "&&", $r[0]);
			$r[0] = str_replace("|", "||", $r[0]);
			//$r[0] = str_replace("^", "^^", $r[0]);//need an eqivelent
			$q = preg_replace("/[A-Z ]/", "", $r[1]);
			if ($q == NULL) {
				$r[1] = trim($r[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_var({$r[1]}, 1);";
			}
			else if ($q == "!") {
				$r[1] = trim(str_replace("!", "", $r[1]));
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_var({$r[1]}, 0);";
			}
			else if ($q == "+") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 1);";
			}
			else if ($q == "|") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_or_case({$tmp[0]}, {$tmp[1]}, 1, 1);";
			}
			else if ($q == "^") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_xor_case({$tmp[0]}, {$tmp[1]}, 1, 1);";
			}
			else if ($q == "!+") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 1);";
			}
			else if ($q == "+!") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 0);";
			}
			else if ($q == "!+!") {
				$tmp = explode("+", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 0);";
			}
			else if ($q == "!|") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 1);";
			}
			else if ($q == "|!") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 0);";
			}
			else if ($q == "!|!") {
				$tmp = explode("|", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 0);";
			}
			else if ($q == "!^") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 1);";
			}
			else if ($q == "^!") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 1, 0);";
			}
			else if ($q == "!^!") {
				$tmp = explode("^", $r[1]);
				$tmp[0] = trim($tmp[0]);
				$tmp[1] = trim($tmp[1]);
				$fn_rules[] = "\tif ({$r[0]})";
				$fn_rules[] = "\t\tset_and_case({$tmp[0]}, {$tmp[1]}, 0, 0);";
			}
			//
		}
	}
	$fn_rules[] = "}";

	/*Debug (uncomment to use)*/
	//var_dump($rules, $facts, $queries);
	var_dump($fn_done, $fn_trues, $fn_rules);
?>