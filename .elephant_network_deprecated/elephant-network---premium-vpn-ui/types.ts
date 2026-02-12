
export enum AppView {
  LOGIN = 'LOGIN',
  REGISTER = 'REGISTER',
  FORGOT_PASSWORD = 'FORGOT_PASSWORD',
  DASHBOARD = 'DASHBOARD',
  PROFILE = 'PROFILE',
  NODES = 'NODES',
  SHOP = 'SHOP'
}

export interface UserData {
  email: string;
  expiryDate: string;
  balance: number;
  remainingDays: number;
  totalData: number;
  usedData: number;
  isPremium: boolean;
}

export interface ProxyNode {
  id: string;
  name: string;
  country: string;
  type: string;
  latency: number;
}
