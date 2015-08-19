<html>
<head>
	<title>Support Hub</title>
	<link rel="stylesheet" href="<?php echo plugins_url( 'assets/css/social.css', _DTBAKER_SUPPORT_HUB_CORE_FILE_ );?>" />
	<script src="//code.jquery.com/jquery-1.11.3.min.js"></script>
    <link href="//fonts.googleapis.com/css?family=Roboto:400,100,300,700" rel="stylesheet" type="text/css">
</head>
<style type="text/css">
    body{
        background: #1E201F;
        -webkit-font-smoothing: antialiased;
        font-family: "Roboto", "Helvetica Neue", Helvetica, sans-serif;
        font-size: 14px;
        font-weight: 400;
        line-height: 1.6;
    }
    #shub_page{
        max-width: 1200px;
        margin:40px auto;
    }
    #shub_wrapper h1{
        text-align: center;
        color: #FFF;
        margin: 0;
        padding: 0 0 27px;;
    }
    #shub_content{
        border-radius: 5px;
        padding: 20px 40px 30px;
        background: #fff;
        position: relative;
    }
    #shub_content:before{
        background: #FFFFFF;
        border-radius: 2px 0 0 0;
        content: "";
        display: block;
        height: 20px;
        left: 50%;
        margin-left: -10px;
        position: absolute;
        transform: rotate(45deg);
        top: -10px;
        width: 20px;
    }
    @media (min-width: 640px) {
        #shub_wrapper {
            padding-left: 10.0%;
            padding-right: 10.0%;
        }
    }
    @media (min-width: 1024px){
        #shub_wrapper {
            padding-left: 20%;
            padding-right: 20%;
        }
    }
    .submit_button{
        background: #769e44;
        color: #FFFFFF;
        margin: 20px 0;
        display: block;
        border: none;
        border-radius: 5px;
        font-size: 18px;
        font-weight: 300;
        padding: 10px 20px;
        text-align: center;
        -webkit-user-select: none;
        font-family: inherit;
        line-height: 1.6;
        text-decoration: none;
    }
</style>
<body>

<div id="shub_page">
    <div id="shub_wrapper">
        <h1>Support Hub</h1>
        <div id="shub_content">