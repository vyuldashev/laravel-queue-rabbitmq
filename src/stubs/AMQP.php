<?php

define('AMQP_NOPARAM', 0);
define('AMQP_DURABLE', 2);
define('AMQP_PASSIVE', 4);
define('AMQP_EXCLUSIVE', 8);
define('AMQP_AUTODELETE', 16);
define('AMQP_INTERNAL', 32);
define('AMQP_NOLOCAL', 64);
define('AMQP_AUTOACK', 128);
define('AMQP_IFEMPTY', 256);
define('AMQP_IFUNUSED', 512);
define('AMQP_MANDATORY', 1024);
define('AMQP_IMMEDIATE', 2048);
define('AMQP_MULTIPLE', 4096);
define('AMQP_NOWAIT', 8192);
define('AMQP_REQUEUE', 16384);

define('AMQP_EX_TYPE_DIRECT', 'direct');
define('AMQP_EX_TYPE_FANOUT', 'fanout');
define('AMQP_EX_TYPE_TOPIC', 'topic');
define('AMQP_EX_TYPE_HEADERS', 'headers');
define('AMQP_OS_SOCKET_TIMEOUT_ERRNO', 536870947);

