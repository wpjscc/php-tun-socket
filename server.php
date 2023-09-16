<?php

require './vendor/autoload.php';

use React\EventLoop\Loop;
use Evenement\EventEmitter;


class Tun extends EventEmitter {

    protected $tun;

    public function __construct($address = '10.10.10.1/24')
    {
        if (!is_resource ($TUN = tuntap_new (null, TUNTAP_DEVICE_TUN))) {
            die ('Failed to create TAP-Device' . "\n");
        }
        
        $Interface = tuntap_name ($TUN);
        
        echo 'Created ', $Interface, "\n";
        
        run_command ('ip link set ' . $Interface . ' up');
        run_command ('ip addr add '.$address.' dev ' . $Interface);
        
        // Read Frames from the device
        echo 'Waiting for frames...', "\n";


        Loop::addReadStream($TUN, function ($fd) {
            $data = fread($fd, 1522);
            var_dump($data);
            $this->emit('data', [$data]);
        });

        $this->tun = $TUN;
        
    }

    public function write($buffer)
    {
        fwrite($this->tun, $buffer);
    }
    public function close()
    {
        fclose($this->tun);
        Loop::removeReadStream($this->tun);

    }
}

$tun = new Tun();

$socket = new React\Socket\SocketServer('0.0.0.0:9080');

$socket->on('connection', function (React\Socket\ConnectionInterface $connection) use ($tun) {
  
    $connection->on('data', function($buffer) use ($tun) {
        $tun->write($buffer);
    });

    $tun->on('data', function ($buffer) use ($connection) { 
        $connection->write($buffer);
    });

    $connection->on('close', function() use ($tun) {
        echo 'Connection closed', "\n";
    });
});


function run_command ($Command) {
    echo '+ ', $Command, "\n";
    
    $rc = 0;
    
    passthru ($Command, $rc);
    
    if ($rc != 0)
      echo '+ Command returned ', $rc, "\n";
    
    return ($rc == 0);
  }


