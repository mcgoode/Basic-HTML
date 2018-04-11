<?php

/*
 * This PHP script just does the standard FizzBuzz exercise
 */


// set up loop from 1 to 100
for ($i = 1; $i <=100; $i++){

	// set up some vars to store results
	$isMultipleOfThree = ( ($i%3) == 0 );// if multiple of 3 Fizz
	$isMultipleOfFive  = ( ($i%5) == 0 );// if multiple of 5 Buzz

	// if multiple of 3 and 5 FizzBuzz
	if( $isMultipleOfThree && $isMultipleOfFive ){
		echo "FizzBuzz!! <br/>";
	}
	elseif ( $isMultipleOfFive ){
		echo "Buzz <br/>";
	}
	elseif( $isMultipleOfThree ){
		echo "Fizz <br/>";
	}else{
		echo "$i <br/>";
	}


}