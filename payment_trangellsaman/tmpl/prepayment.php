<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_k2store
 * @subpackage 	Trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access'); ?>

<form action="<?php echo @$vars->sendUrl; ?>" method="post" name="adminForm" target="_self">
	<p><?php echo 'درگاه بانک سامان' ?></p>
	<input type="submit" class="k2store_cart_button btn btn-primary" value="<?php echo JText::_($vars->button_text); ?>" />
    <input type='hidden' name='Amount' value='<?php echo @$vars->totalAmount; ?>'>
    <input type='hidden' name='MID' value='<?php echo @$vars->samanmerchantId; ?>'>
    <input type='hidden' name='ResNum' value='<?php echo @$vars->reservationNumber; ?>'>
    <input type='hidden' name='RedirectURL' value='<?php echo @$vars->callBackUrl; ?>'>
	<br />
</form>
