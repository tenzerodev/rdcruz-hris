/**
 * Philippine payroll for 2026: SSS, PhilHealth and Pag-IBIG contributions, and withholding tax on compensation.
 * All amounts are in pesos; results are rounded to centavos. No dependencies; runs in Node and browsers.
 *
 * Rates (check them against the agencies' current circulars before running a real payroll):
 * - SSS: 15% of the Monthly Salary Credit (employee 5%, employer 10%), MSC ₱5,000–₱35,000 in ₱500 steps.
 *   The part of the MSC above ₱20,000 goes to the Mandatory Provident Fund (MySSS Pension Booster).
 *   Employees' Compensation (employer): ₱10 below an MSC of ₱15,000, ₱30 from ₱15,000.
 * - PhilHealth: 5% of the monthly basic salary, floor ₱10,000, ceiling ₱100,000, shared equally.
 * - Pag-IBIG: 2% each on up to ₱10,000 a month (employee 1% at ₱1,500 or less).
 * - Withholding tax: the BIR tables for compensation from January 1, 2023 (TRAIN law, RA 10963).
 *   Minimum wage earners are exempt. 13th month pay and other benefits are tax-free up to ₱90,000 a year.
 */

export const RATES_2026 = {
  sss: { rate: 0.15, employee: 0.05, employer: 0.10, mscMin: 5000, mscMax: 35000, mscStep: 500, regularMax: 20000, ec: { low: 10, high: 30, threshold: 15000 } },
  philhealth: { rate: 0.05, floor: 10000, ceiling: 100000 },
  pagibig: { employee: 0.02, employeeLow: 0.01, lowThreshold: 1500, employer: 0.02, maxFundSalary: 10000 },
  benefitsExemption: 90000,
};

/** BIR withholding tax tables on compensation, effective January 1, 2023: [over, base tax, rate on the excess]. */
export const TAX_TABLES = {
  monthly: [[0, 0, 0], [20833, 0, 0.15], [33333, 1875, 0.20], [66667, 8541.80, 0.25], [166667, 33541.80, 0.30], [666667, 183541.80, 0.35]],
  'semi-monthly': [[0, 0, 0], [10417, 0, 0.15], [16667, 937.50, 0.20], [33333, 4270.70, 0.25], [83333, 16770.70, 0.30], [333333, 91770.70, 0.35]],
  annual: [[0, 0, 0], [250000, 0, 0.15], [400000, 22500, 0.20], [800000, 102500, 0.25], [2000000, 402500, 0.30], [8000000, 2202500, 0.35]],
};

const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
const PERIODS = { monthly: 1, 'semi-monthly': 2 };

/** Monthly Salary Credit for a monthly compensation: the nearest ₱500 step, from ₱5,000 to ₱35,000. */
export function sssMsc(monthly, rates = RATES_2026.sss) {
  const step = Math.floor((monthly + rates.mscStep / 2) / rates.mscStep) * rates.mscStep;
  return Math.min(rates.mscMax, Math.max(rates.mscMin, step));
}

export function sss(monthly, rates = RATES_2026.sss) {
  const msc = sssMsc(monthly, rates);
  const ec = msc < rates.ec.threshold ? rates.ec.low : rates.ec.high;
  return {
    msc,
    regular_msc: Math.min(msc, rates.regularMax),
    mpf_msc: Math.max(0, msc - rates.regularMax),
    employee: r2(msc * rates.employee),
    employer: r2(msc * rates.employer),
    ec,
    total: r2(msc * rates.rate + ec),
  };
}

export function philhealth(monthlyBasic, rates = RATES_2026.philhealth) {
  const base = Math.min(rates.ceiling, Math.max(rates.floor, monthlyBasic));
  const premium = r2(base * rates.rate);
  const employee = r2(premium / 2);
  return { base, premium, employee, employer: r2(premium - employee) };
}

export function pagibig(monthly, rates = RATES_2026.pagibig) {
  const base = Math.min(monthly, rates.maxFundSalary);
  const employeeRate = monthly <= rates.lowThreshold ? rates.employeeLow : rates.employee;
  return { base, employee: r2(base * employeeRate), employer: r2(base * rates.employer) };
}

/** Withholding tax for one pay period's taxable compensation. */
export function withholdingTax(taxable, frequency = 'monthly') {
  const table = TAX_TABLES[frequency];
  if (!table) throw new Error(`Unknown frequency "${frequency}". Use monthly, semi-monthly or annual.`);
  const [over, base, rate] = [...table].reverse().find(([o]) => taxable >= o);
  return r2(Math.max(0, base + (taxable - over) * rate));
}

export const annualTax = (taxable) => withholdingTax(taxable, 'annual');

/**
 * One employee's pay for a pay period.
 * @param {object} p
 * @param {number} p.monthlyBasic          basic salary for the month
 * @param {'monthly'|'semi-monthly'} [p.frequency]
 * @param {number} [p.taxableAllowances]   other taxable pay for the month (allowances, commissions, overtime)
 * @param {number} [p.nonTaxable]          de minimis and other tax-free pay for the month
 * @param {boolean} [p.minimumWageEarner]  exempt from income tax
 * @param {number} [p.otherDeductions]     loans, cash advances (after tax) for the month
 */
export function computePay({ monthlyBasic, frequency = 'monthly', taxableAllowances = 0, nonTaxable = 0, minimumWageEarner = false, otherDeductions = 0 }) {
  if (!(monthlyBasic >= 0)) throw new Error('monthlyBasic must be 0 or more.');
  const n = PERIODS[frequency];
  if (!n) throw new Error('frequency must be monthly or semi-monthly.');

  // Contributions are based on the month's pay. They are split evenly across the pay periods.
  const compensation = monthlyBasic + taxableAllowances;
  const s = sss(compensation), ph = philhealth(monthlyBasic), pi = pagibig(compensation);
  const monthlyContributions = r2(s.employee + ph.employee + pi.employee);

  const per = (x) => r2(x / n);
  const gross = per(monthlyBasic + taxableAllowances + nonTaxable);
  const contributions = per(monthlyContributions);
  const taxable = minimumWageEarner ? 0 : r2(per(compensation) - contributions);
  const tax = minimumWageEarner ? 0 : withholdingTax(taxable, frequency);
  const deductions = r2(contributions + tax + per(otherDeductions));

  return {
    frequency,
    gross,
    basic: per(monthlyBasic),
    taxable_allowances: per(taxableAllowances),
    non_taxable: per(nonTaxable),
    contributions: {
      sss: { msc: s.msc, employee: per(s.employee), employer: per(s.employer), ec: per(s.ec), mpf_msc: s.mpf_msc },
      philhealth: { base: ph.base, employee: per(ph.employee), employer: per(ph.employer) },
      pagibig: { base: pi.base, employee: per(pi.employee), employer: per(pi.employer) },
      employee_total: contributions,
      employer_total: per(s.employer + s.ec + ph.employer + pi.employer),
    },
    taxable,
    withholding_tax: tax,
    other_deductions: per(otherDeductions),
    deductions,
    net_pay: r2(gross - deductions),
    employer_cost: r2(gross + per(s.employer + s.ec + ph.employer + pi.employer)),
  };
}

/** 13th month pay: total basic salary earned in the year ÷ 12, and the part above the tax-free ceiling. */
export function thirteenthMonth(basicEarnedInYear, otherBenefits = 0, rates = RATES_2026) {
  const pay = r2(basicEarnedInYear / 12);
  return { pay, taxable_excess: r2(Math.max(0, pay + otherBenefits - rates.benefitsExemption)) };
}
