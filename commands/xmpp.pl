#!/usr/bin/perl
use strict;
use Net::XMPP;
my ($recip, $msg) = @ARGV;
if(! $recip || ! $msg) {
    print 'Syntax: $0 <recipient> <message>\n';
    exit;
}
my $con = new Net::XMPP::Client(
    debuglevel=>1,
    

);
my $status = $con->Connect(
    hostname => 'spiesshofer.com',
    connectiontype => 'tcpip');
die('ERROR: XMPP connection failed') if ! defined($status);
my @result = $con->AuthSend(
    hostname => 'spiesshofer.com',
    username => 'server@spiesshofer.com',
    password => 'wiaudeer');
die('ERROR: XMPP authentication failed') if $result[0] ne 'ok';
die('ERROR: XMPP message failed')
    if ($con->MessageSend(to => $recip, body => $msg) != 0);
print 'Success!\n';