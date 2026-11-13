import test from 'node:test'
import assert from 'node:assert/strict'
import { formatMask, maskModelValue, rawMaskValue, isEmailFormat } from '../../resources/js/Taildash/components/masks/format.ts'

test('documents retain leading zeroes, ignore punctuation and cap numeric lengths', () => {
  assert.equal(formatMask('01234567890', 'cpf'), '012.345.678-90')
  assert.equal(formatMask('01.234.567/0001-89', 'cnpj'), '01.234.567/0001-89')
  assert.equal(formatMask('12345678901234567', 'cnpj'), '12.345.678/9012-34')
  assert.equal(formatMask('123abc456', 'cpf'), '123.456')
  assert.equal(formatMask('', 'cpf'), '')
})

test('combined document switches between CPF and CNPJ without losing digits', () => {
  assert.equal(formatMask('01234567890', 'document'), '012.345.678-90')
  assert.equal(formatMask('012345678901', 'document'), '01.234.567/8901')
  assert.equal(formatMask('01234567890123', 'document'), '01.234.567/8901-23')
})

test('phone uses an optional fifth subscriber digit and accepts formatted paste', () => {
  assert.equal(formatMask('3133334444', 'phone'), '(31) 3333-4444')
  assert.equal(formatMask('31999998888', 'phone'), '(31) 99999-8888')
  assert.equal(formatMask('(31) 99999-8888', 'phone'), '(31) 99999-8888')
  assert.equal(formatMask('31333344449999', 'phone'), '(31) 33334-4449')
  assert.equal(formatMask('31', 'phone'), '(31')
})

test('currency is formatted from cents without floating point arithmetic', () => {
  assert.equal(formatMask('1', 'currency'), '0,01')
  assert.equal(formatMask('12', 'currency'), '0,12')
  assert.equal(formatMask('12345', 'currency'), '123,45')
  assert.equal(formatMask('R$ 1.234,56', 'currency'), '1.234,56')
  assert.equal(maskModelValue('12345', 'currency'), 'R$ 123,45')
  assert.equal(rawMaskValue('R$ 0,01', 'currency'), '1')
  assert.equal(rawMaskValue('R$ 123,45', 'currency'), '12345')
  assert.equal(formatMask('999999999999999', 'currency'), '9.999.999.999.999,99')
  assert.equal(rawMaskValue('R$ 123.456.789.012.345.678,90', 'currency'), '12345678901234567890')
  assert.equal(formatMask('000000', 'currency'), '0,00')
  assert.equal(maskModelValue('', 'currency'), '')
})

test('email preserves plus-addressing and case while removing whitespace', () => {
  assert.equal(formatMask(' Nome+tag@example.com.br ', 'email'), 'Nome+tag@example.com.br')
  assert.equal(isEmailFormat('Nome+tag@example.com.br'), true)
  assert.equal(isEmailFormat('nome@@example.com'), false)
  assert.equal(isEmailFormat('nome@example'), false)
})

test('postal code formatting and raw document extraction', () => {
  assert.equal(formatMask('30130110', 'cep'), '30130-110')
  assert.equal(rawMaskValue('(31) 3333-4444', 'phone'), '3133334444')
  assert.equal(rawMaskValue('012.345.678-90', 'cpf'), '01234567890')
})
