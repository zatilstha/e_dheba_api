<form  action="https://hblpgw.2c2p.com/HBLPGW/Payment/Payment/Payment" method="post">
    <input type="hidden" id="paymentGatewayID" name="paymentGatewayID" value="{{$merchantId}}" />
    <input type="hidden" id="amounts" name="amount" value="{{$amount}}" />
    <input type="hidden" id="invoiceNo" name="invoiceNo" value="{{$invoiceNo}}"/>
    <input type="hidden" id="productDesc" name="productDesc" value="Ride Payment" />
    <input type="hidden" id="currencyCode" name="currencyCode" value="{{$currencyCode}}"/>
    <input type="hidden" id="userDefined1" name="userDefined1" value="{{$userDefined1}}"/>
    <input type="hidden" id="nonSecure" name="nonSecure" value="{{$nonSecure}}"/>
    <input type="hidden" id="hashValue" name="hashValue" value="{{$signData}}"/>
    <br>
</form>
<script type="text/javascript">
    var form = document.forms[0];
    form.submit();
</script>