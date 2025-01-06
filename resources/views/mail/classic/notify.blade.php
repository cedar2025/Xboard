<body style="font-family: Lato; font-size: 14px; line-height: 19px;">
        <div style="padding: clamp(1.13rem, -0.03rem + 2.4vw, 2.38rem) clamp(0.75rem, -0.4rem + 2.4vw, 2rem);
            background-color: rgb(255, 255, 255);
            box-shadow: rgba(0, 0, 0, 0.16) 0px 3px 6px 0px;
            overflow: auto;">
            <div style="padding: clamp(1.25rem, 0.56rem + 1.44vw, 2rem) clamp(1rem, 0.08rem + 1.92vw, 2rem);
            border-top: 1px solid rgb(204, 204, 204);
            flex-direction: column;">
                <!--Status Start-->
                <table data-flex-wrap="wrap" data-max-width="1000" data-gap="10" >
                    <tr>
                        <td style="display: table-cell; height: 100%;">
                            <img src="https://img.alicdn.com/imgextra/i3/O1CN01qcJZEf1VXF0KBzyNb_!!6000000002662-2-tps-384-92.png" id="p_lt_imgLogo"
                                class="img-responsive hidden-sm hidden-xs" alt="ABZ Packaging" width="210"
                                height="144" style="border: none;">
                            <span style="display: block;">Unit A11/2a Westall Rd, Clayton VIC 3168, Australia | 61 3 9018 5678</span>

                            <span></span>
                        </td>
                        <td style="display: table-cell; height: 100%;">
                            <label style="font-size:20px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">YOUR ORDER</label>
                            <label style="font-size: 12px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">Order No:{%Order.OrderInvoiceNumber#%}</label>
                            <label style="font-size: 12px;display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">Order Placed: {%Format(Order.OrderDate, "{0:dd/MM/yyyy}"#%}</label>
                        </td>
                    </tr>
                </table>
                <!--Status End-->
                <div style="padding: 24px 0">
                    <hr>
                </div>
                <!--Delivery Information Start-->
                <div  style="margin-bottom: 20px;">
                    <div>
                        <div style="display: table-cell; height: 100%;">
                            <label style=" display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">
                                <strong style="font-size: 14px; font-weight: 700;">Delivery Information</strong>
                            </label>
                        </div>
                    </div>
                    <div data-max-width="1000" data-mobile-layout="column">
                        <div style="display: table-cell; height: 100%;">
                            <label style="font-size: 12px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">Delivery Type</label>
                            <span>{%ShippingOption.ShippingOptionDisplayName#%}</span>
                        </div>
                    </div>
                </div>
                <div data-max-width="1000" data-mobile-layout="column">
                    <div style="display: table-cell; height: 100%;">
                        <label style="font-size: 12px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">Delivery Address</label>
                        <span>{%IfEmpty(Customer.CustomerCompany, "", HTMLEncode(Customer.CustomerCompany) + "<br />")#%}</span>
                        <span>
                            {%IfEmpty(ShippingAddress.AddressPersonalName, "", HTMLEncode(ShippingAddress.AddressPersonalName) + "<br />")#%}{%HTMLEncode(ShippingAddress.AddressFirstName)#%}
                            {%HTMLEncode(ShippingAddress.AddressLastName)#%}
                            {% IfEmpty(ShippingAddress.AddressPhone, "", "Tel: " + HTMLEncode(ShippingAddress.AddressPhone) + "<br />") #%}
                            {%HTMLEncode(ShippingAddress.AddressLine1)#%},
                            {%HTMLEncode(ShippingAddress.AddressLine2)==""?"":HTMLEncode(ShippingAddress.AddressLine2)+","#%}
                            {%HTMLEncode(ShippingAddress.AddressCity)#%},
                            {%HTMLEncode(ShippingAddress.AddressState.DisplayName)#%},
                            {%HTMLEncode(ShippingAddress.AddressZip)#%},{%HTMLEncode(ShippingAddress.AddressCountry.CountryDisplayName)#%}.
                        </span>
                        {% IfEmpty(Order.OrderNote, "", "<div style='margin-bottom:5px'>
                            <span style='display: inline; font-weight: 700; font-size: 14px;'>Order Note:</span> 
                            <span style='display: inline;'>" + HTMLEncode(Order.OrderNote) + "</span></div>") #%}
                    </div>
                </div>
                <!--Delivery Information End-->
                <div style="padding: 24px 0">
                    <hr>
                </div>
                <div >
                    <div data-max-width="1000" data-mobile-layout="column" style="margin-bottom: 10px;">
                        <div style="display: table-cell; height: 100%;">
                            <label style=" display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">
                                <strong style="font-size: 14px; font-weight: 700;">Order Summary</strong>
                            </label>
                        </div>
                    </div>
                    <div>
                        <div data-layout="full" >
                            <label>{%Order.OrderItems.Count#%} Item(s)</label>
                            
                        </div>
                    </div>
                </div>
                <div style="padding: 20px 0;">
                    <hr>
                </div>
                <!--item start-->
                {%foreach (item in Order.OrderItems) { %}
                <table data-mobile-layout="column">
                    <tr data-layout="split" style="display: table-cell; height: 100%;">
                        <td style="width: 90px; max-width: 90px; ">
                            <img alt="{%item.OrderItemSKUName%}"
                                src="https://{%CurrentSite.SiteDomainName #%}{% GetProductImageUrl(item.OrderItemSKUID,100,100)%}">
                        </td>
                        <td style="padding-left: 20px;">
                            <p style="font-family: Lato;font-weight: 400; font-size: 15px;line-height: 18px;">{%item.OrderItemSKUName%}</p>
                            <p style="font-family: Lato;font-weight: 400; font-size: 15px;line-height: 18px;"> {%item.OrderItemSKU.SKUNumber%}</p>
                            <p style="font-family: Lato; font-size: 14px; line-height: 17px; margin: 3px 0px;">Quantity : {%item.OrderItemUnitCount%}</p>
                            <p style="font-family: Lato; font-size: 14px; line-height: 17px; margin: 3px 0px; font-weight: 700;">
                                {%FormatPrice(item.OrderItemTotalPrice)%}</p>
                            {%if (GetOrderItemDiscount(item.OrderItemID).Any()) {%}
                            {%foreach (x in GetOrderItemDiscount(item.OrderItemID)) { %}
                            <p style="color:#D12510">***Special offers: {%StripTags(x.Name)%}
                                <strong>(-{%(x.Value == item.OrderItemTotalPrice ? "Limited Time Offer!!! " : "") + FormatPrice(x.Value)%})</strong>
                            </p>
                            {%}%}
                            {%}%}
                        </td>
                    </tr>
                </table>{%}#%}
                <!--item end-->
                <div style="padding: 24px 0">
                    <div data-layout="full" style="display: block;">
                        <hr>
                    </div>
                </div>
                <!--Price-->
                <table data-gap="8" style="width: 100%; font-size: inherit;">
                    <tr>
                        <td style="font-weight: 700;">
                            <span>Subtotal:</span>
                        </td>
                        <td style="font-weight: 700; text-align: right;">
                            <span>{%FormatPrice(Order.OrderItems.Sum("OrderItemTotalPrice")#%}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700;">
                            <span>Total GST 10%:</span>
                        </td>
                        <td style="font-weight: 700;text-align: right;">
                            <span>{%FormatPrice(Order.OrderTotalTax#%}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700;">
                            <span>Shipping</span>
                        </td>
                        <td style="font-weight: 700;text-align: right;">
                            <span>{%FormatPrice(Order.OrderTotalShipping#%}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 700;">
                            <span>Discounts:<font style="color: red;">
                                    {%HTMLEncode(GetOrderCouponCodes(Order.OrderCouponCodes))#%}</font></span>
                        </td>
                        <td style="font-weight: 700;text-align: right;">
                            <span style="color: red;text-align: right;">-
                                {%FormatPrice(TotalCartItemLevelDiscount(GetShoppingCartInfoFromOrder(Order.OrderID))+TotalCartLevelDiscount(GetShoppingCartInfoFromOrder(Order.OrderID)))#%}
                            </span>
                        </td>
                    </tr>
                    
                    <tr>
                        <td colspan="2" style="padding: 0;">
                            <hr>
                        </td>
                        
                    </tr>
                    <tr>
                        <td style="font-weight: 700;">
                            <span>Total:</span>
                        </td>
                        <td style="font-weight: 700;text-align: right;">
                            <span>{%FormatPrice(Order.OrderGrandTotal)#%}</span>
                        </td>
                    </tr>
                </table>
                
                <div style="padding: 24px 0">
                    <hr>
                </div>
                <div data-gap="10" >
                    <div>
                        <div style="display: table-cell; height: 100%;">
                            <label style="font-size: 14px;">
                                <strong>Payment Information</strong>
                            </label>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <div style="display: table-cell; height: 100%;">
                            <label style="font-size: 12px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">Billing Address</label>
                            <span>{%IfEmpty(Customer.CustomerCompany, "", HTMLEncode(Customer.CustomerCompany) + "<br />")#%}
                                {%IfEmpty(ShippingAddress.AddressPersonalName, "", HTMLEncode(ShippingAddress.AddressPersonalName) + "<br />")#%}
                                {%HTMLEncode(BillingAddress.AddressFirstName)#%}
                                {%HTMLEncode(BillingAddress.AddressLastName)#%}
                                {% IfEmpty(BillingAddress.AddressPhone, "", "Tel: " + HTMLEncode(BillingAddress.AddressPhone) + "<br />") #%}
                                {%HTMLEncode(BillingAddress.AddressLine1)#%},
                                {%HTMLEncode(BillingAddress.AddressLine2)==""?"":HTMLEncode(BillingAddress.AddressLine2)+","#%}
                                {%HTMLEncode(BillingAddress.AddressCity)#%},
                                {%HTMLEncode(BillingAddress.AddressState.DisplayName)#%},
                                {%HTMLEncode(BillingAddress.AddressZip)#%},{%HTMLEncode(BillingAddress.AddressCountry.CountryDisplayName)#%}.</span>
                        </div>
                    </div>
                </div>

                <div style="display: table; width: 100%; table-layout: fixed; margin-top: 20px">
                    <div style="display: table-row;">
                        <div style="display: table-cell; width: 50%;">
                            <label style="font-size: 12px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">PaidBy:
                            </label><span>{%Order.OrderPaymentOption.DisplayName#%}</span>
                        </div>
                        <div style="display: table-cell; width: 50%; text-align: right;">
                            <label style="font-size: 12px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">Discount Code</label>
                            <span style="color: red;">{%Order.OrderCouponCodes#%}</span>
                        </div>
                    </div>
                </div>
                <div style="padding: 24px 0;">
                    <hr>
                </div>
                <!--Returns & Refunds Policy Start-->
                <div >
                    <div >
                        <div style="display: table-cell; height: 100%;">
                            <label style="font-size: 14px; display: block; font-family: Lato; font-weight: 600; line-height: 14px; text-transform: uppercase; letter-spacing: calc(0.1em); margin: 5px 0px;">
                                <strong>Returns & Refunds Policy</strong>
                            </label>
                        </div>
                    </div>
                    <!--Returns & Refunds Policy Text Start-->
                    <div class="container">
                        <div style="font-family: Calibri; font-size: 14px; line-height: 1.5;">
                            CUSTOMERS MUST NOTIFY ABZ PACKAGING CUSTOMER SUPPORT IN WRITING TO
                            <a href="mailto:sales@abzpackaging.com.au">sales@abzpackaging.com.au</a>
                            WITHIN 14 DAYS OF RECEIPT OF GOODS IF THEY HAVE RECEIVED THE INCORRECT PRODUCT OR IT
                            DOES NOT MEET REQUIREMENTS.
                            CUSTOMERS THEN HAVE 30 DAYS TO RETURN THE ITEM IF APPROVED BY ABZ PACKAGING.
                            READ THE FULL POLICY HERE:
                            <a href="https://www.abzpackaging.com.au/returns-refund-policy" target="_blank">
                                www.abzpackaging.com.au/returns-refund-policy
                            </a>
                        </div>
                    </div>
                    <!--Returns & Refunds Policy End-->
                </div>
            </div>
        </div>
</body>