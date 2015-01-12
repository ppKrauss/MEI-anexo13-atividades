<?php
/**
 * "CVS to Wiki-do-MEI", conversão de arquivos CSV da "Lei do MEI, ANEXO XIII"
 * para formatação wikitext da Mediawiki.
 * https://github.com/ppKrauss/MEI-anexo13-atividades
 * 
 * Configurar e usar em modo terminal:
 *  % php lib/cvs2wikiMEI.php > dados/anexo13-wikiPage.txt
 * em seguida editar na Wiki os termos desejados para os grandes grupos (~30).
 *
 * NOTA: como as adaptação ocorrem via Wiki antes do XML, o melhor é wiki2xml,
 *  %php wiki2xml.php < wikiPage.txt > db.xml
 *
 */

// // // // // // // // //
// //  BEGIN:CONFIGURAÇÃO
	$CHARSET = 'UTF-8'; // MUDAR PARA ISO SE PRECISAR, O CORRETO E-PING É OFERECER TXT UTF8
	$FILE = 'dados/Anexo-XIII-resCGSN94.csv';
	$SEP = "\t";
	$nmax = 0;   // usar por ex. 100 para teste.
	$deltaMAX = 99;
	$FC = array('OCUPAÇÃO', 'REF-ALT', 'CNAE', 'DESCRIÇÃO SUBCLASSE CNAE', 'ISS', 'ICMS');
		//       0           1           2      3                           4      5
	$flag_relatSublasse = 0; // debug com 1
	$flag_relatMain = 1; 	 // debug com 0 ou 1	
	$flag_outMode = 'wikisimple'; // 'wiki', 'wikisimple' ou 'xml'. Ver out_tag(). 
// // END:CONFIGURAÇÃO
// // // // // // // // // // // //


// // // // // // // // // // // //
// //  BEGIN:CARGA (pelo prefixo)
$FC[] = "LINHA";
$h = fopen($FILE,'r');
$n=$n2=0;
$antCnae = 0;
$all=array();
while( !feof($h) && (!$nmax || $n<$nmax) ) {
	$n++;
	$lin = fgetcsv($h,0,$SEP); // 0 ou max. length por performance
	$codCnae = $lin[2];
	if ( isset($lin[0]) && (int)$codCnae>0 ) {
		array_push($lin,$n);
		$codCnae_prefix = substr($codCnae,0,4);		
		if (!$antCnae || !isset($all[$codCnae_prefix]) ) {
			$antCnae = $codCnae_prefix;
			$all[$antCnae]=array($lin);
		} else
			array_push($all[$codCnae_prefix],$lin);
		$n2++;
	} elseif (trim($codCnae) && $codCnae!='CNAE')
		print "\n###ERRO na linha $n (em codCNAE=$codCnae): ".join('|',$lin);
} // loop file
fclose($h);
// // END:CARGA
// // // // // // // // // // // //

$NGR = count($all);
print "\n((Carga com $n2 linhas em $NGR grupos, depois de ler $n linhas))\n";
ksort($all,SORT_NUMERIC); 


// AGRUPAMENTO POR PREFIXOS VIZINHOS (distando deltaMAX da referencia)
$allWiki = array();
$allWiki_IMP = array();
$allWikiByCod = array();
$n=$NGR2=$prefAnt = 0;
$BR = $flag_relatMain? '': "\n";
foreach($all as $prefixCnae=>$lista) {
	$N = count($lista);
	$NGR2++;
	$descrCNAE = rmEsp($lista[0][3]);
	$hash = toHash($descrCNAE);
	list($lista,$Nits) = ($N>1)?
		array( $lista,           out_onetag('itensCNAE',array('n'=>$N,'prefixCnae'=>$prefixCnae)) ):
		array( array($lista[0]), ''                              );
	$allWiki[$hash] = "$BR=== $descrCNAE ===$Nits\n";
	$allWiki_IMP[$hash] = '';
	// RELATORIO DO PREFIXO:
	foreach($lista as $lin) {
		list($ocupacao,$refalter,$codCnae,$descrCNAE,$iss,$icms,$Nlin) = $lin;
		$n++;
		$ii = showIMP($iss,$icms);
		$allWiki[$hash] .= out_onetag('itemCNAE', array('codCnae'=>$codCnae,'ocupacao'=>$ocupacao,'taxas'=>$ii), "\n", "\n");
		$allWiki_IMP[$hash] .= $ii;	
	}
	// Arquivando em $allWikiByCod e gambi para a parte IMP:
	$delta = abs((int)$prefixCnae - (int)$prefAnt);
	if (!$prefAnt || !$prefixCnae || $delta>$deltaMAX){
		$prefAnt = $prefixCnae;
		$allWikiByCod[$prefAnt] = array($allWiki[$hash],$allWiki_IMP[$hash]);
	} else {
		$allWikiByCod[$prefAnt][] = $allWiki[$hash];
		$allWikiByCod[$prefAnt][] = $allWiki_IMP[$hash];
	}
}

if ($flag_relatSublasse) { // relatório alternativo para conferir subclasses
	print "\n*****Sublasses CNAE*****\n";
	ksort($allWiki);
	foreach($allWiki as $k=>$show)
		print $show;
	print "\n\n((FIM n=$n linhas registradas, $NGR2 grupos))\n";
}

if ($flag_relatMain) {
	ksort($allWikiByCod,SORT_NUMERIC);
	$nn=0;
	foreach($allWikiByCod as $k=>$show) {
		$nn = count($show);
		//$nn = preg_match_all('/(=== )/s',$show,$m)? count($m[0]): 0;
		if ($nn>2){
			$icms = $iss = $tmp = '';
			$aux = array(); // GAMBI por não ter organizado os dados
			foreach ($show as $sw0) {
				$sw = $sw0;
				$sw = preg_replace('/ *ISS ( e)? */s','',$sw);
				if ($sw!=$sw0) $iss='ISS';
				$tmp = trim(preg_replace('/ *ICMS */s','',$sw), ' e');		
				if ($tmp>'') // confere se era linha de dado ou de imposto
					$aux[]=$sw0;
				elseif ($tmp!=$sw)  // tinha icms
					$icms = 'ICMS';
			} // for
			sort($aux);
			// por ex. agrupamento do prefixo CNAE 1122 com mais 4 prefixos - ICMS
			$ii = showIMP($iss,$icms,'');
			print "\n== X (não-CNAE) ==\n{{grupoPrefixCNAE|$k|$nn|$ii}}\n\n".join("\n",$aux);		
		} else {
			print preg_replace('/===/s','==',"$show[0]");
		}
	} // for
} // if flag


//// 
function rmEsp($s){
	return preg_replace('/(E (PRODUTOS? )?)?NÃO ESPECIFICADOS?.+$/uis','',$s);
}

function toHash($s) { // dispensável.. usando por inércia
	return preg_replace('/[\s_\(\),;\.]+/uis','_',$s);
}

function showIMP($iss,$icms,$sp='') { // notação dos impostos
	$ii = ($iss && $iss!='N')? "{$sp}ISS": '';
	return  ($icms && $icms!='N')? (($ii? "$ii e ": $sp).'ICMS'): '';
}


// Output na forma de tag "xml", "wiki" (template Mediawiki) ou "wikisimple" (posicional)
function out_onetag($name,$attribs,$SEPini="\n",$SEPend='') {
	global $flag_outMode; // xml, wiki ou wikisimple=sem nomes de atributos
	if ($flag_outMode=='wikisimple'){
		$str_attribs = join('|',array_values($attribs));
		$TAG = "{{{$name}|$str_attribs}}";		
	} else {
		$flagXML = ($flag_outMode=='xml');
		$lst_attribs = array();
		foreach ($attribs as $k=>$v) {
			$lst_attribs[] = $flagXML? "$k=\"$v\"": "$k=$v";
		}
		$TAG = $flagXML?
			("<$name".join(' ',$lst_attribs).'/>'): 
			("{{{$name}|".join('|',$lst_attribs).'}}');
	}
	return "$SEPini$TAG$SEPend";
}
?>