# Let's Ngage !

Ngage is a simple comment system that does exactly what it implies - Provide engagement for users by way of chat.
No added layer of abstractions or catchy gimmicks. at least not at the moment 


# Getting started
To get started with Ngage, simply clone this repo or download and unzip to your http server. 

> Ngage server is built on php 8.*** and will require a server with php installed

## Dependencies
Ngage is as lightweight as they come - 100% dependency free however, this is a self hosted version and will require you to have a server running with php v8+ and mySQL database 

## Setup & Config
Ngage can be set up and running with these simple steps

 1. clone or unzip the the Ngage repo on your server
 2. Add a comments table to your database using the Ngage.sql provided
 3. update the Ngage.php file to configure database access.

	    <?php $db =  new  mysqli('localhost',  'root',  'password',  'dbName') or  die('DB connection failed'); ?>


 4. Include Ngage Css and Js Files in your application
 

	    <link rel="stylesheet" href="/Ngage/Ngage.css" />
	    <body>
	    <div id="htmlID"></div>
	    <script  src="/Ngage/Ngage.js"></script>
 6. Ngage

On the frontend Engage is called by a single javascript class ***Ngage*** 

  

    const myCommentBox = new Ngage({...config}); 
    myCommentBox.init();    

it's actually that simple.

**Minimal Setup Example**

     <html>
	   <head>
	    <link rel="stylesheet" href="/Ngage/Ngage.css" />
	   </head>  
	   <body>
	    <div id="htmlID"></div>
	    <script  src="/Ngage/Ngage.js"></script>
	    <script>
	        const myCommentBox = new Ngage({
	        anchor:"#htmlID",
	        id:"homePageComments",
	        userId:"user-01",
	        username:"John Doe",
	        }); 
		    myCommentBox.init();    
	    </script>
	   </body>
	</html>

## Config Options
To get your instance of Ngage Up and running, you will need to define configuration properties

    const config = { anchor:"#htmlID", id:"homePage", userID:"user-one", username:"john doe" }

**Available Options**

 - **anchor**  (required) -  The id or classname of the html element your engage instance will be bulit on.
 - **id** (required) - The id of your engage instance *(this is useful for using separate instances on different pages on your application Likewise if you want to repeat the same instance you may do so by providing the same ID)* 
 - **userID** (optional) - The userID property attaches a user session to an Ngage instance. 
 -  **username** (optional) - This is the display name of a user when they leave a comment 

> Note: If the **userID** or **username** property is not provided, your Ngage instance wll run properly but the comment field will be disabled. 

 - **theme**(optional) - Available themeing to suit your applications UI. available theme options are "light" and "dark".   By default, an Ngage instance will run on the "light" theme
 - **endpoint** (application crucial) -  The endpoint property defines the location of the Ngage.php file. By default the endpoint is automatically defined to be sitting in the same folder as the Ngage.js and the rest of Ngage application Files however, in situations where you may need to move the php file to a separate location you need to point the endpoint property to that location.
    
	    { endpoint:'path/to/Ngage.php'; }

