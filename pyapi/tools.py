import re

# 乘
def toWei(amount, decimal):
    num = amount * pow(10, decimal)
    return num

# 除
def fromWei(amount, decimal):
    # print(amount)
    num = '{:.16f}'.format(float(amount) / pow(10, decimal))
    return num
    