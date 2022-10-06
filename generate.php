#!/usr/bin/env php
<?php

function lcp($a, $b) {
	$a = strval($a);
	$b = strval($b);
	$a = mb_strtolower($a);
	$b = mb_strtolower($b);
	for ($i=0,$e=mb_strlen($a) ; $i<$e ; ++$i) {
		if (mb_substr($a, $i, 1) !== mb_substr($b, $i, 1)) {
			return mb_substr($a, 0, $i);
		}
	}
	return $a;
}

chdir(__DIR__);
putenv('LD_PRELOAD=libtcmalloc_minimal.so');

$fst_ana = trim(shell_exec('ls -1t ~/langtech/kal/src/analyser-disamb-gt-desc.hfstol /usr/share/giella/kal/analyser-disamb-gt-desc.hfstol 2>/dev/null | head -n1'));
$fst_gen = trim(shell_exec('ls -1t ~/langtech/kal/src/generator-gt-norm.hfstol /usr/share/giella/kal/generator-gt-norm.hfstol 2>/dev/null | head -n1'));

if (empty($fst_ana) || empty($fst_gen)) {
	fprintf(STDERR, "Empty FST\n");
	exit(-1);
}

//*
fprintf(STDERR, "Analyzing words\n");
$lines = explode("\n", trim(file_get_contents('input.txt')));
$lines = array_chunk($lines, count($lines)/8 + 1);
foreach ($lines as $i => $chunk) {
	file_put_contents('/tmp/dict-spell.in.'.$i, implode("\n", $chunk)."\n");
}
file_put_contents('/tmp/dict-spell.sh', <<<XOUT
#!/bin/bash

rm -rf /tmp/dict-spell.out.*
sort /tmp/dict-spell.in.0 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.0 &
sort /tmp/dict-spell.in.1 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.1 &
sort /tmp/dict-spell.in.2 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.2 &
sort /tmp/dict-spell.in.3 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.3 &
sort /tmp/dict-spell.in.4 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.4 &
sort /tmp/dict-spell.in.5 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.5 &
sort /tmp/dict-spell.in.6 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.6 &
sort /tmp/dict-spell.in.7 | uniq | hfst-optimized-lookup -u -p $fst_ana | uniq > /tmp/dict-spell.out.7 &

for job in `jobs -p`
do
	wait \$job
done

sort /tmp/dict-spell.out.* | uniq

XOUT
);
shell_exec('/bin/bash /tmp/dict-spell.sh > /tmp/dict-spell.out');
//*/

$exist = [];
$lexs = [];
$outs = explode("\n", trim(file_get_contents('/tmp/dict-spell.out')));
foreach ($outs as $out) {
	$out = trim($out);
	if (empty($out)) {
		continue;
	}

	$out = explode("\t", $out, 2);
	if (strpos($out[0], ' ') !== false && strpos($out[1], '+?') !== false) {
		continue;
	}

	$exist[$out[0]] = true;
	if (strpos($out[1], '+?') !== false) {
		continue;
	}

	if (preg_match('~\+(N|Num|Prop|Pron)(\+Abs\+(?:Sg|Pl))$~', $out[1], $m)) {
	}
	else if (preg_match('~\+(V)(\+Ind\+3Sg(?:\+3SgO)?)$~', $out[1], $m)) {
	}
	else if (preg_match('~\+(Interj)$~', $out[1], $m)) {
	}
	else {
		//fprintf(STDERR, "Reject: %s\n", $out);
		continue;
	}

	$base = mb_substr($out[1], 0, mb_strpos($out[1], '+'));
	$lexs[$out[0]][$m[1]][$base][$out[1]] = true;
}

$map = [];
$in = [];
foreach ($lexs as $lex => $anas) {
	foreach ($anas as $wc => $bs) {
		foreach ($bs as $base => $as) {
			foreach ($as as $a => $_) {
				$n = str_replace('+Abs+', '+Rel+', $a);
				if ($n !== $a) {
					$map[$n][$lex][$wc][$base][$a] = true;
					$in[] = $n;
				}
				$n = preg_replace('~\+Sg$~', '+Pl', $a);
				if ($n !== $a) {
					$map[$n][$lex][$wc][$base][$a] = true;
					$in[] = $n;
				}
				$n = preg_replace('~((?:\+Gram/[TI]V)?(?:\+Gram/Refl)?)?\+V\+~', '+NNGIT+Der/vv$1+V+', $a);
				if ($n !== $a) {
					$map[$n][$lex][$wc][$base][$a] = true;
					$in[] = $n;
				}
			}
		}
	}
}
sort($in);
$in = array_unique($in);

//*
fprintf(STDERR, "Generating inflections\n");
$lines = array_chunk($in, count($in)/8 + 1);
foreach ($lines as $i => $chunk) {
	file_put_contents('/tmp/dict-spell-gen.in.'.$i, implode("\n", $chunk)."\n");
}
file_put_contents('/tmp/dict-spell-gen.sh', <<<XOUT
#!/bin/bash

rm -rf /tmp/dict-spell-gen.out.*
sort /tmp/dict-spell-gen.in.0 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.0 &
sort /tmp/dict-spell-gen.in.1 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.1 &
sort /tmp/dict-spell-gen.in.2 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.2 &
sort /tmp/dict-spell-gen.in.3 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.3 &
sort /tmp/dict-spell-gen.in.4 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.4 &
sort /tmp/dict-spell-gen.in.5 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.5 &
sort /tmp/dict-spell-gen.in.6 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.6 &
sort /tmp/dict-spell-gen.in.7 | uniq | hfst-optimized-lookup -u -p $fst_gen | uniq > /tmp/dict-spell-gen.out.7 &

for job in `jobs -p`
do
	wait \$job
done

cat /tmp/dict-spell-gen.out.* | sort | uniq

XOUT
);
shell_exec('/bin/bash /tmp/dict-spell-gen.sh > /tmp/dict-spell-gen.out');
//*/

$rv = [];

$outs = file_get_contents('/tmp/dict-spell-gen.out');
$outs = explode("\n", trim($outs));
foreach ($outs as $out) {
	$out = trim($out);
	if (empty($out) || strpos($out, '+?') !== false) {
		continue;
	}

	$out = explode("\t", $out);
	if (!array_key_exists($out[0], $map)) {
		fprintf(STDERR, "Missing: %s\n", $out[0]);
		continue;
	}

	foreach ($map[$out[0]] as $lex => $anas) {
		foreach ($anas as $wc => $bs) {
			foreach ($bs as $base => $as) {
				foreach ($as as $a => $_) {
					if (strpos($out[0], '+Rel+') !== false) {
						$w = 'Rel';
					}
					else if (strpos($out[0], '+Abs+Pl') !== false) {
						$w = 'Pl';
					}
					else if (strpos($out[0], '+NNGIT+Der/vv+V+') !== false) {
						$w = 'NNGIT';
					}
					$rv[$lex][$wc][$base][$w][$out[1]] = true;
				}
			}
		}
	}
}

$used = [];
$out = [];
foreach ($rv as $lex => $anas) {
	ksort($anas);
	foreach ($anas as $wc => $bs) {
		krsort($bs);
		foreach ($bs as $base => $as) {
			krsort($as);

			$cs = [$lex];
			foreach ($as as $w => $fxs) {
				$cs = array_merge($cs, array_keys($fxs));
			}
			sort($cs);
			$a = $cs[0];
			$p = lcp($a, array_pop($cs));
			$lp = mb_strlen($p);

			$olang = false;
			$plur = !preg_match('~^(V|Interj)$~', $wc);
			foreach (array_keys($lexs[$lex][$wc][$base]) as $k) {
				if (strpos($k, '+OLang/') !== false) {
					$olang = true;
				}
				if (preg_match('~\+Sg$~', $k)) {
					$plur = false;
					break;
				}
			}

			$flex = [];
			foreach ($as as $w => $fxs) {
				$ofx = [];
				foreach ($fxs as $fx => $_) {
					$fx = mb_substr($fx, $lp);
					if (mb_strlen($fx)) {
						$ofx[] = '–'.$fx;
					}
				}
				if (count($ofx) == 0) {
					$flex[] = '-';
				}
				else if (count($ofx) == 1) {
					$flex[] = $ofx[0];
				}
				else {
					$flex[] = '{'.implode(', ', $ofx).'}';
				}
			}

			$o = '';
			if ($olang) {
				$o .= '_';
			}
			$o .= mb_strtolower($lex)."\t";
			if ($lp !== mb_strlen($lex)) {
				$o .= mb_substr($lex, 0, $lp).'¦'.mb_substr($lex, $lp)."\t";
			}
			else {
				$o .= "$lex\t";
			}
			$o .= $wc;
			if ($plur) {
				$o .= ", pl.";
			}
			$o .= "\t";
			//$o .= "$base\t";
			$o .= implode(', ', $flex);
			if ($lp === 0) {
				$o = str_replace('–', '', $o);
				$o = str_replace('¦', '', $o);
			}
			$out[] = $o;

			$used[$lex] = true;
		}
	}
}

foreach ($used as $k => $_) {
	unset($lexs[$k]);
	unset($exist[$k]);
}
foreach ($lexs as $lex => $anas) {
	unset($exist[$lex]);
	foreach ($anas as $wc => $bs) {
		$out[] = mb_strtolower($lex)."\t$lex\t$wc\t(no generation)";
	}
}

shell_exec('rm -f *.tsv');

file_put_contents("failed.txt", "Lexeme\n", FILE_APPEND);
foreach ($exist as $lex => $wcs) {
	file_put_contents("failed.txt", "$lex\n", FILE_APPEND);
}

sort($out);
$out = array_unique($out);
foreach ($out as $o) {
	$f = mb_substr($o, 0, 1);
	$o = substr($o, strpos($o, "\t")+1)."\n";
	if (!file_exists("dict-$f.tsv")) {
		file_put_contents("dict-$f.tsv", "Lexeme\tWC\tFlexion\n", FILE_APPEND);
	}
	file_put_contents("dict-$f.tsv", $o, FILE_APPEND);
	echo $o;
}

rename('dict-_.tsv', 'dict-loan.tsv');
