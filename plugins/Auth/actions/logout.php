<?php

Atomik_Auth::logout();
Atomik::redirect(Atomik::get('request/from', Atomik::appUrl('/')), false);