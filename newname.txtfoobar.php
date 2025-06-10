<?php

for($i = 1; $i <= 100; $i++ ){
    if($i % 15 === 0){
        echo"foobar";
    }elseif ($i % 3 === 0){
        echo"foo";
    }elseif ($i % 5 === 0){
        echo"bar";
    }else{
        echo $i;
    }
    // Add comma and space
    if($i < 100){
        echo" , ";
    }
}
?>