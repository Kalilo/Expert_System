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
				$rules[] = trim("{$tmp[0]} => {$tmp[1]}");
				$rules[] = trim("{$tmp[1]} => {$tmp[0]}");
			}
			else
				$rules[] = $line;
		}
		else if (strlen($line) > 1 && $line[0] == "=" && $line[1] != ">")
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

	/*Extract Relavent rules*/
	$q = NULL;
	foreach ($queries as $querie) {
		$l = strlen($querie);
		$k = 0;
		while (++$k < $l) {
			if (strpos($q, $querie[$k]) === FALSE)
				$q .= $querie[$k];
		}
	}
	$changed = 1;
	$f = NULL;
	while ($changed) {
		$changed = 0;
		foreach ($rules as $rule) {
			$l = strlen($q);
			$k = -1;
			while ((++$k < $l) && isset($rule)) {
				if (preg_match("/=>.*{$q[$k]}.*/", $rule)) {
					$tmp = preg_replace("/[ \|\+\!\=\>\<\^\(\)]/", "", $rule);
					$l2 = strlen($tmp);
					$k2 = -1;
					while (++$k2 < $l2) {
						if (strpos($q, $tmp[$k2]) === FALSE) {
							$q .= $tmp[$k2];
							$changed = 1;
						}
					}
					if ($f == FALSE || in_array($rule, $f) === FALSE) {
						$f[] = $rule;
						unset($rule);
					}
				}
			}
		}
	}
	$rules = $f;

	/*Generate done function*/
	$fn_done[0] = "char\tdone(void)";
	$fn_done[1] = "{";
	$fn_display[0] = "void\tdisplay(void)";
	$fn_display[1] = "{";
	foreach ($queries as $querie) {
		$querie = strtolower($querie);
		$k = 0;
		$l = strlen($querie);
		while (!empty($querie) && (++$k) < $l) {
			$fn_done[] = "\tif ({$querie[$k]} != 0 && {$querie[$k]} != 1)";
			$fn_done[] = "\t\treturn (1);";
			$fn_display[] = "\tif ({$querie[$k]})";
			$fn_display[] = '		write(1, "' . $querie[$k] . ' is true.\n", 11);';
			$fn_display[] = "\telse";
			$fn_display[] = '		write(1, "' . $querie[$k] . ' is false.\n", 12);';
		}
	}
	$fn_done[] = "\treturn (0);";
	$fn_done[] = "}";
	$fn_display[] = "}";

	/*Generate trues and display function*/
	$fn_trues[0] = "void\ttrues(void)";
	$fn_trues[1] = '{';
	foreach ($facts as $fact) {
		$fact = strtolower($fact);
		$k = 0;
		$l = strlen($fact);
		while (!empty($fact) && (++$k) < $l) {
			$fn_trues[] = "\t{$fact[$k]} = 3;";
		}
	}
	$fn_trues[] = "}";

	/*Generate rules function*/
	$fn_rules[0] = "void\trules(void)";
	$fn_rules[1] = "{";
	if ($rules)
	foreach ($rules as $rule) {
		$rule = strtolower($rule);
		if (preg_match("/==>/", $rule)) {
			$r = explode("==>", $rule);
			$r[0] = trim($r[0]);
			$r[0] = str_replace("+", "&&", $r[0]);
			$r[0] = str_replace("|", "||", $r[0]);
			if (strpos($r[0], "^") > 0) {
				if (preg_match("/([a-z]) \^ ([a-z])/", $r[0])) {
					$r[0] = preg_replace("/([a-z]) \^ ([a-z])/", "($1 & 0x1) ^ ($2 & 0x1)", $r[0]);
				}
				if (preg_match("/(\(.+[a-z])\) \^ ([a-z])/", $r[0])) {
					$r[0] = preg_replace("/(\(.+[a-z])\) \^ ([a-z])/", "($1) & 0x1) ^ ($2 & 0x1)", $r[0]);
				}
				if (preg_match("/(\(.+[a-z])\) \^ \(([a-z].+\))/", $r[0])) {
					$r[0] = preg_replace("/(\(.+[a-z])\) \^ \(([a-z].+\))/", "($1) & 0x1) ^ (($2 & 0x1)", $r[0]);
				}
				if (preg_match("/([a-z]) \^ \(([a-z].+\))/", $r[0])) {
					$r[0] = preg_replace("/([a-z]) \^ \(([a-z].+\))/", "($1 & 0x1) ^ (($2 & 0x1)", $r[0]);
				}
			}
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
			if (strpos($r[0], "^") > 0) {
				if (preg_match("/([a-z]) \^ ([a-z])/", $r[0])) {
					$r[0] = preg_replace("/([a-z]) \^ ([a-z])/", "($1 & 0x1) ^ ($2 & 0x1)", $r[0]);
				}
				if (preg_match("/(\(.+[a-z])\) \^ ([a-z])/", $r[0])) {
					$r[0] = preg_replace("/(\(.+[a-z])\) \^ ([a-z])/", "($1) & 0x1) ^ ($2 & 0x1)", $r[0]);
				}
				if (preg_match("/(\(.+[a-z])\) \^ \(([a-z].+\))/", $r[0])) {
					$r[0] = preg_replace("/(\(.+[a-z])\) \^ \(([a-z].+\))/", "($1) & 0x1) ^ (($2 & 0x1)", $r[0]);
				}
				if (preg_match("/([a-z]) \^ \(([a-z].+\))/", $r[0])) {
					$r[0] = preg_replace("/([a-z]) \^ \(([a-z].+\))/", "($1 & 0x1) ^ (($2 & 0x1)", $r[0]);
				}
			}
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
	system("if [ -f expert_system.c ]; then rm expert_system.c;fi");
	$command = system("cp ./C\ Program/share.c ./expert_system.c");
	/*writing the $fn_done, $fn_true, $fn_rules to same file*/
	$fd = fopen("./expert_system.c", "a+");
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
	$command = system("gcc expert_system.c -o expert_system", $retval);
	if ($retval === 1)
		die("Syntax Error in rules." . PHP_EOL);
	$command = system("./expert_system");
	/*Debug (uncomment to use)*/
	//var_dump($rules, $facts, $queries);
	//var_dump($fn_done, $fn_trues, $fn_display, $fn_rules);
?>
