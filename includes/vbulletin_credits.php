<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.2 - Nulled By VietVBB Team
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2013 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

// display the credits table for use in admin/mod control panels

print_form_header('index', 'home');
print_table_header($vbphrase['vbulletin_developers_and_contributors']);
print_column_style_code(array('white-space: nowrap', ''));
print_label_row('<b>' . $vbphrase['software_developed_by'] . '</b>', '
	vBulletin Solutions, Inc.,
	Internet Brands, Inc.
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['business_product_development'] . '</b>', '
	Alan Chiu,
	Daniel Lee,
	Gary Carroll,
	John McGanty,
	Marjo Mercado,
	Neal Sainani,
	Olga Mandrosov,
	Omid Majdi
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['engineering'] . '</b>', '
	David Grove,
	Edwin Brown,
	Jay Quiambao,
	Jin-Soo Jo,
	Kevin Sours,
	Michael Perez,
	Paul Marsden
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['qa'] . '</b>', '
	Allen Lin,
	Andrew Vo,
	Fei Leung,
	Meghan Sensenbach,
	Michael Mendoza,
	Miguel Montaño,
	Sebastiano Vassellatti,
	Yves Rigaud
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['support'] . '</b>', '
	Aakif Nazir,
	Christine Tran,
	D\'Marco Brown,
	Dominic Schlatter,
	Duane Piosca,
	George Liu,
	Joe DiBiasi,
	Lynne Sands,
	Mark Bowland,
	Rene Jimenez,
	Wayne Luke,
	Zachery Woods
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['special_thanks_and_contributions'] . '</b>', '
	Ace Shattock,
	Adrian Harris,
	Adrian Sacchi,
	Ahmed,
	Ajinkya Apte,
	Alan Orduno,
	Ali Madkour,
	Anders Pettersson,
	Andreas Kirbach,
	Andrew Elkins,
	Andy Huang,
	Aston Jay,
	Billy Golightly,
	bjornstrom,
	Bob Pankala,
	Brad Wright,
	Brett Morriss,
	Brian Swearingen,
	Brian Gunter,
	Carrie Anderson,
	Chen Avinadav,
	Chevy Revata,
	Chris Holland,
	Christian Hoffmann,
	Christopher Riley,
	Colin Frei,
	Danco Dimovski,
	Daniel Clements,
	Darren Gordon,
	David Bonilla,
	David Webb,
	Danny Morlette,
	David Yancy,
	Dody,
	digitalpoint,
	Don Kuramura,
	Don T. Romrell,
	Doron Rosenberg,
	Elmer Hernandez,
	Emon Khan,
	Enrique Pascalin,
	Eric Johney,
	Eric Sizemore (SecondV),
	Freddie Bingham,
	Fabian Schonholz,
	Fernando Munoz,
	Fernando Varesi,
	Floris Fiedeldij Dop,
	Harry Scanlan,
	Gavin Robert Clarke,
	Geoff Carew,
	Giovanni Martinez,
	Green Cat,
	Glenn Vergara,
	Hanafi Jamil,
	Hani Saad,
	Hanson Wong,
	Hartmut Voss,
	Ivan Anfimov,
	Ivan Milanez,
	Jacquii Cooke,
	Jake Bunce,
	Jan Allan Zischke,
	Jasper Aguila,
	Jaume L&oacute;pez,
	Jelle Van Loo,
	Jen Rundell,
	Jeremy Dentel,
	Jerry Hutchings,
	Joan Gauna,
	Joanna W.H.,
	Joe Rosenblum,
	Joe Velez,
	Joel Young,
	John Jakubowski,
	John Percival,
	John Yao,
	Jonathan Javier Coletta,
	Jorge Tiznado,
	Joseph DeTomaso,
	Justin Turner,
	Kay Alley,
	Kevin Connery,
	Kevin Schumacher,
	Kevin Wilkinson,
	Kier Darby,
	Kira Lerner,
	Kolby Bothe,
	Kyle Furlong,
	Kym Farnik,
	Lamonda Steele,
	Lawrence Cole,
	Lisa Swift,
	Marco Mamdouh Fahem,
	Mark Hennyey,
	Mark James,
	Mark Jean,
	Marlena Machol,
	Martin Meredith,
	Maurice De Stefano,
	Matthew Gordon,
	Merjawy,
	Mert Gokceimam,
	Michael Anders,
	Michael Biddle,
	Michael Fara,
	Michael Henretty,
	Michael Kellogg,
	Michael \'Mystics\' K&ouml;nig,
	Michael Lavaveshkul,
	Michael Pierce,
	Michael Miller,
	Michlerish,
	Mike Sullivan,
	Milad Kawas Cale,
	miner,
	Nathan Wingate,
	nickadeemus2002,
	Ole Vik,
	Oscar Ulloa,
	Overgrow,
	Peggy Lynn Gurney,
	Prince Shah,
	Pritesh Shah,
	Priyanka Porwal,
	Pieter Verhaeghe,
	Reenan Arbitrario,
	Refael Iliaguyev,
	Reshmi Rajesh,
	Riasat Al Jamil,
	Ricki Kean,
	Rob (Boofo) Hindal,
	Robert Beavan White,
	Roms,
	Ruth Navaneetha,
	Ryan Ashbrook,
	Ryan Royal,
	Sal Colascione III,
	Scott MacVicar,
	Scott Molinari,
	Scott William,
	Scott Zachow,
	Shawn Vowell,
	Sophie Xie,
	Stefano Acerbetti,
	Stephan \'pogo\' Pogodalla,
	Steve Machol,
	Sven "cellarius" Keller,
	Tariq Bafageer,
	The Vegan Forum,
	ThorstenA,
	Tom Murphy,
	Tony Phoenix,
	Trevor Hannant,
	Torstein H&oslash;nsi,
	Troy Roberts,
	Tully Rankin,
	Vinayak Gupta,
	Xiaoyu Huang,
	Yasser Hamde,
	Zoltan Szalay,
	Zuzanna Grande
	', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['copyright_enforcement_by'] . '</b>', '
	vBulletin Solutions, Inc.
', '', 'top', NULL, false);
print_table_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded
|| # CVS: $RCSfile$ - $Revision: 77639 $
|| ####################################################################
\*======================================================================*/
?>
